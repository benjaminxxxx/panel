<?php

namespace App\Consultas;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta de RUC y DNI en las páginas públicas de SUNAT (antes: proyecto peru-consult-api).
 *
 * Por qué fallaba "a veces" el servicio anterior: su cliente HTTP era UNO solo para todo el
 * servidor y guardaba las cookies de SUNAT acumulándolas (nunca reemplazaba la sesión vieja).
 * Tras unas consultas mandaba varias JSESSIONID a la vez, y dos consultas simultáneas mezclaban
 * el número aleatorio (numRnd) de una sesión con la cookie de otra: SUNAT devolvía la página de
 * error y el parser respondía "no encontrado". Al reintentar a veces coincidía y funcionaba.
 *
 * Además SUNAT tiene delante un filtro anti-bots (Cloudflare) que a veces responde "Just a
 * moment..." (403) a los servidores. Por eso la fuente principal es el PADRÓN REDUCIDO DEL RUC,
 * el archivo oficial que SUNAT publica para descarga (php artisan consultas:padron): responde al
 * instante y no depende de la web. La página de consulta queda como respaldo para RUC recientes.
 *
 * En la consulta en línea cada intento abre su propia sesión (cookie jar nueva), se reintenta
 * hasta 3 veces solo si la falla es de SUNAT (no si el RUC no existe) y el resultado se guarda en caché.
 */
class ConsultaSunat
{
    public function __construct(private readonly PadronRuc $padron) {}

    public const CONSULTA_RUC = 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/jcrS00Alias';

    public const SOLICITANTE = 'https://ww1.sunat.gob.pe/ol-ti-itatencionf5030/registro/solicitante';

    private const NAVEGADOR = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    private const INTENTOS = 3;

    /**
     * @return array<string, mixed>|null null = el RUC no existe
     *
     * @throws ConsultaNoDisponible si SUNAT no responde tras varios intentos
     */
    public function ruc(string $ruc): ?array
    {
        $clave = "consulta:ruc:{$ruc}";
        if (Cache::has($clave)) {
            return Cache::get($clave) ?: null;
        }
        if ($fila = $this->padron->buscar($ruc)) {
            return $this->desdePadron($fila);
        }
        $html = $this->conReintentos(fn () => $this->paginaRuc(['accion' => 'consPorRuc', 'nroRuc' => $ruc, 'actReturn' => '1', 'modo' => '1']),
            fn (string $h) => str_contains($h, 'list-group-item-heading') || $this->sinResultados($h));

        $datos = $this->parsearRuc($html);
        if ($datos !== null && $datos['ruc'] !== $ruc) {
            $datos = null; // SUNAT respondió otra cosa: no confiar
        }
        Cache::put($clave, $datos ?? [], $datos ? now()->addHours(12) : now()->addMinutes(10));

        return $datos;
    }

    /**
     * DNI: primero el proveedor configurado (RENIEC vía terceros, si hay token), luego el
     * formulario de SUNAT y por último el padrón de RUC (personas con RUC 10).
     *
     * @return array<string, mixed>|null null = no se encontró en ninguna fuente
     */
    public function dni(string $dni): ?array
    {
        $clave = "consulta:dni:{$dni}";
        if (Cache::has($clave)) {
            return Cache::get($clave) ?: null;
        }
        $datos = $this->dniProveedor($dni) ?? $this->dniPadronLocal($dni) ?? $this->dniSolicitante($dni) ?? $this->dniPadronRuc($dni);
        Cache::put($clave, $datos ?? [], $datos ? now()->addDays(30) : now()->addMinutes(10));

        return $datos;
    }

    // ---------------------------------------------------------------- Padrón

    /**
     * @param  array<string, string|null>  $f
     * @return array<string, mixed>
     */
    private function desdePadron(array $f): array
    {
        return [
            'ruc' => $f['ruc'], 'razon_social' => $f['razon_social'], 'nombre_comercial' => '', 'tipo' => '',
            'estado' => $f['estado'], 'condicion' => $f['condicion'], 'direccion' => $f['direccion'],
            'ubigeo' => $f['ubigeo'], 'departamento' => '', 'provincia' => '', 'distrito' => '',
            'actividades' => [], 'comprobantes_electronicos' => [], 'padrones' => [],
            'agente_retencion' => false, 'buen_contribuyente' => false, 'fuente' => 'padron',
        ];
    }

    /**
     * Quien tiene RUC 10 aparece en el padrón como 10 + DNI + dígito: su nombre es el de la persona.
     *
     * @return array<string, string>|null
     */
    private function dniPadronLocal(string $dni): ?array
    {
        $fila = DB::table('padron_ruc')->where('ruc', 'like', "10{$dni}_")->first();

        return $fila ? ['dni' => $dni, 'nombres' => '', 'apellido_paterno' => '', 'apellido_materno' => '',
            'nombre_completo' => $fila->razon_social, 'ruc' => $fila->ruc, 'fuente' => 'padron'] : null;
    }

    // ---------------------------------------------------------------- RUC

    /**
     * Una sesión nueva: entra, pide el número aleatorio y hace la consulta con la misma cookie.
     *
     * @param  array<string, string>  $consulta
     */
    private function paginaRuc(array $consulta): string
    {
        $cookies = new CookieJar;
        $http = $this->cliente($cookies);
        $http->get(self::CONSULTA_RUC);
        $pagina = $http->asForm()->post(self::CONSULTA_RUC, ['accion' => 'consPorRazonSoc', 'razSoc' => 'BVA FOODS'])->body();
        if (! preg_match('/name="numRnd" value="([^"]*)"/', $pagina, $m)) {
            throw new ConsultaNoDisponible('SUNAT no entregó el número de sesión');
        }

        return $http->asForm()->withHeaders(['Referer' => self::CONSULTA_RUC])
            ->post(self::CONSULTA_RUC, $consulta + ['numRnd' => $m[1]])->body();
    }

    private function sinResultados(string $html): bool
    {
        return (bool) preg_match('/no existe|no se encontr|no hay contribuyente/i', $html);
    }

    /** @return array<string, mixed>|null */
    public function parsearRuc(string $html): ?array
    {
        [$textos, $tablas] = $this->camposDeFicha($this->xpath($html));
        $cabecera = $textos['Número de RUC:'] ?? '';
        if (! str_contains($cabecera, '-')) {
            return null;
        }
        [$ruc, $razon] = array_map('trim', explode('-', $cabecera, 2));
        [$direccion, $departamento, $provincia, $distrito] = $this->domicilio($textos['Domicilio Fiscal:'] ?? '');
        $padrones = $tablas['Padrones:'] ?? [];
        $comprobantes = $textos['Comprobantes Electrónicos:'] ?? '-';
        $comercial = $textos['Nombre Comercial:'] ?? '-';

        return [
            'ruc' => $ruc,
            'razon_social' => $razon,
            'nombre_comercial' => $comercial === '-' ? '' : $comercial,
            'tipo' => $textos['Tipo Contribuyente:'] ?? '',
            'estado' => $this->primeraLinea($textos['Estado del Contribuyente:'] ?? ''),
            'condicion' => $this->primeraLinea($textos['Condición del Contribuyente:'] ?? ''),
            'direccion' => $direccion,
            'departamento' => $departamento,
            'provincia' => $provincia,
            'distrito' => $distrito,
            'actividades' => array_values(array_filter($tablas['Actividad(es) Económica(s):'] ?? [])),
            'comprobantes_electronicos' => $comprobantes === '-' ? [] : array_map('trim', explode(',', $comprobantes)),
            'padrones' => array_values(array_filter($padrones, fn (string $p) => $p !== '' && $p !== 'NINGUNO')),
            'agente_retencion' => (bool) preg_grep('/Agentes de Retenci/i', $padrones),
            'buen_contribuyente' => (bool) preg_grep('/Buenos Contribuyentes/i', $padrones),
        ];
    }

    /**
     * Ficha de SUNAT: cada bloque es "Título: valor" o "Título:" con una tabla de valores.
     *
     * @return array{0: array<string, string>, 1: array<string, list<string>>}
     */
    private function camposDeFicha(DOMXPath $x): array
    {
        $textos = [];
        $tablas = [];
        foreach ($this->elementos($x, "//div[contains(concat(' ', normalize-space(@class), ' '), ' list-group ')]/div") as $item) {
            $titulos = $this->textosDe($x, ".//*[contains(@class, 'list-group-item-heading')]", $item);
            $valores = $this->textosDe($x, ".//*[contains(@class, 'list-group-item-text')]", $item);
            if (count($titulos) === 2 && $valores === []) {
                $textos[$titulos[0]] = $titulos[1];

                continue;
            }
            foreach ($titulos as $i => $titulo) {
                if (isset($valores[$i])) {
                    $textos[$titulo] = $valores[$i];
                } else {
                    $tablas[$titulo] = $this->textosDe($x, './/table//td', $item);
                }
            }
        }

        return [$textos, $tablas];
    }

    /** @return list<DOMElement> */
    private function elementos(DOMXPath $x, string $consulta, ?DOMNode $contexto = null): array
    {
        $encontrados = [];
        foreach ($x->query($consulta, $contexto) ?: [] as $nodo) {
            if ($nodo instanceof DOMElement) {
                $encontrados[] = $nodo;
            }
        }

        return $encontrados;
    }

    /** @return list<string> */
    private function textosDe(DOMXPath $x, string $consulta, ?DOMNode $contexto = null): array
    {
        return array_map(fn (DOMElement $e) => trim($e->textContent), $this->elementos($x, $consulta, $contexto));
    }

    /**
     * "AV. X NRO. 1 LIMA          - LIMA          - SAN ISIDRO" → [dirección, dep, prov, dist].
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function domicilio(string $texto): array
    {
        $partes = preg_split('/\s{2,}-\s*/', trim($texto)) ?: [];
        if (count($partes) !== 3) {
            return [trim((string) preg_replace('/\s+/', ' ', $texto)), '', '', ''];
        }
        $primera = trim((string) preg_replace('/\s+/', ' ', $partes[0]));
        $departamento = '';
        foreach (['MADRE DE DIOS', 'SAN MARTIN', 'LA LIBERTAD', 'CALLAO', 'AMAZONAS', 'ANCASH', 'APURIMAC', 'AREQUIPA', 'AYACUCHO',
            'CAJAMARCA', 'CUSCO', 'HUANCAVELICA', 'HUANUCO', 'ICA', 'JUNIN', 'LAMBAYEQUE', 'LIMA', 'LORETO', 'MOQUEGUA', 'PASCO',
            'PIURA', 'PUNO', 'TACNA', 'TUMBES', 'UCAYALI'] as $dep) {
            if (str_ends_with($primera, ' '.$dep)) {
                $departamento = $dep;
                $primera = trim(substr($primera, 0, -strlen($dep)));
                break;
            }
        }

        return [$primera, $departamento, trim($partes[1]), trim($partes[2])];
    }

    private function primeraLinea(string $texto): string
    {
        return trim(strtok($texto, "\r\n") ?: '');
    }

    // ---------------------------------------------------------------- DNI

    /**
     * Proveedor externo configurado (CONSULTA_DNI_URL con {dni}, CONSULTA_DNI_TOKEN).
     *
     * @return array<string, string>|null
     */
    private function dniProveedor(string $dni): ?array
    {
        $url = (string) config('consultas.dni_url');
        if ($url === '') {
            return null;
        }
        try {
            $r = Http::timeout(8)->acceptJson()->withToken((string) config('consultas.dni_token'))
                ->get(str_replace('{dni}', $dni, $url));
        } catch (ConnectionException $e) {
            Log::warning('consulta DNI: proveedor sin respuesta', ['error' => $e->getMessage()]);

            return null;
        }
        $j = $r->successful() ? $r->json() : null;
        $j = is_array($j) && isset($j['data']) && is_array($j['data']) ? $j['data'] : $j;
        if (! is_array($j)) {
            return null;
        }
        // Formatos habituales: {nombres, apellidoPaterno, apellidoMaterno} o {first_name, first_last_name, second_last_name}.
        $nombres = $j['nombres'] ?? $j['first_name'] ?? null;
        $paterno = $j['apellidoPaterno'] ?? $j['apellido_paterno'] ?? $j['first_last_name'] ?? '';
        $materno = $j['apellidoMaterno'] ?? $j['apellido_materno'] ?? $j['second_last_name'] ?? '';

        return $nombres ? $this->persona($dni, (string) $nombres, (string) $paterno, (string) $materno, 'reniec') : null;
    }

    /**
     * Formulario de atención de SUNAT (el que usaba peru-consult). Hoy suele responder vacío.
     *
     * @return array<string, string>|null
     */
    private function dniSolicitante(string $dni): ?array
    {
        try {
            $j = $this->cliente(new CookieJar)->acceptJson()
                ->post(self::SOLICITANTE, ['tipDocu' => '1', 'numDocu' => $dni, 'tipPers' => '1'])->json();
        } catch (ConnectionException) {
            return null;
        }

        return is_array($j) && ! empty($j['nombreSoli'])
            ? $this->persona($dni, (string) $j['nombreSoli'], (string) ($j['apePatSoli'] ?? ''), (string) ($j['apeMatSoli'] ?? ''), 'sunat')
            : null;
    }

    /**
     * Padrón de RUC por documento: da el nombre de quien tiene RUC 10 (apellidos y nombres juntos).
     *
     * @return array<string, string>|null
     */
    private function dniPadronRuc(string $dni): ?array
    {
        try {
            $html = $this->conReintentos(fn () => $this->paginaRuc([
                'accion' => 'consPorTipdoc', 'razSoc' => '', 'nroRuc' => '', 'nrodoc' => $dni, 'contexto' => 'ti-it', 'modo' => '1',
                'rbtnTipo' => '2', 'search1' => '', 'tipdoc' => '1', 'search2' => $dni, 'search3' => '', 'codigo' => '',
            ]), fn (string $h) => str_contains($h, 'aRucs') || str_contains($h, 'list-group') || $this->sinResultados($h));
        } catch (ConsultaNoDisponible) {
            return null;
        }
        $x = $this->xpath($html);
        foreach ($this->elementos($x, "//a[contains(@class, 'aRucs')]") as $a) {
            $texto = trim((string) preg_replace('/\s+/', ' ', $a->textContent));
            if (preg_match('/RUC:\s*(10\d{9})\s+(.+?)\s+Ubicaci/u', $texto, $m)) {
                return ['dni' => $dni, 'nombres' => '', 'apellido_paterno' => '', 'apellido_materno' => '',
                    'nombre_completo' => $m[2], 'ruc' => $m[1], 'fuente' => 'sunat-padron'];
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function persona(string $dni, string $nombres, string $paterno, string $materno, string $fuente): array
    {
        $nombres = trim($nombres);
        $paterno = trim($paterno);
        $materno = trim($materno);

        return [
            'dni' => $dni, 'nombres' => $nombres, 'apellido_paterno' => $paterno, 'apellido_materno' => $materno,
            'nombre_completo' => trim(preg_replace('/\s+/', ' ', "{$paterno} {$materno} {$nombres}")), 'fuente' => $fuente,
        ];
    }

    // ---------------------------------------------------------------- HTTP

    private function cliente(CookieJar $cookies): PendingRequest
    {
        return Http::withOptions(['cookies' => $cookies])
            ->withUserAgent(self::NAVEGADOR)
            ->timeout(12)
            ->connectTimeout(6);
    }

    /**
     * Reintenta con una sesión nueva mientras SUNAT devuelva algo que no es la respuesta esperada.
     *
     * @param  callable(): string  $consulta
     * @param  callable(string): bool  $valida
     */
    private function conReintentos(callable $consulta, callable $valida): string
    {
        $ultimo = null;
        for ($i = 1; $i <= self::INTENTOS; $i++) {
            try {
                $html = $consulta();
                if ($valida($html)) {
                    return $html;
                }
                $ultimo = 'respuesta inesperada de SUNAT';
            } catch (ConnectionException|ConsultaNoDisponible $e) {
                $ultimo = $e->getMessage();
            }
            if ($i < self::INTENTOS) {
                usleep(300_000 * $i);
            }
        }
        Log::warning('consulta SUNAT sin respuesta', ['error' => $ultimo]);

        throw new ConsultaNoDisponible('SUNAT no responde en este momento. Intenta en unos segundos.');
    }

    private function xpath(string $html): DOMXPath
    {
        if (! mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1'); // SUNAT responde en Latin-1
        }
        $dom = new DOMDocument;
        $previo = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return new DOMXPath($dom);
    }
}
