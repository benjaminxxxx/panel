<?php

namespace App\Facturacion\Core\Validacion;

use App\Facturacion\Core\Calculo\Decimal;
use App\Facturacion\Core\Dominio\Cliente;
use App\Facturacion\Core\Dominio\Detraccion;
use App\Facturacion\Core\Dominio\ItemSolicitud;
use App\Facturacion\Core\Dominio\ResultadoCalculo;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Enums\TipoDocumentoIdentidad;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Reglas de negocio y de SUNAT que se verifican ANTES de consumir un correlativo.
 * Los nombres de campo coinciden con los del payload de la API.
 */
final class ValidadorComprobante
{
    public const ZONA_HORARIA = 'America/Lima';

    /** Días hacia atrás permitidos para la fecha de emisión. */
    public const DIAS_MAXIMOS_ATRASO = 3;

    /** Desde este importe la boleta debe identificar al cliente. */
    public const LIMITE_BOLETA_SIN_IDENTIFICAR = '700.00';

    public const MAX_ITEMS = 500;

    public const MAX_DECIMALES_CANTIDAD = 6;

    public const MAX_DECIMALES_PRECIO = 10;

    /** Unidades de medida permitidas (Catálogo 03 - UN/ECE Rec 20). */
    public const UNIDADES = [
        'NIU' => 'Unidad (bienes)',
        'ZZ' => 'Unidad (servicios)',
        'KGM' => 'Kilogramo',
        'GRM' => 'Gramo',
        'TNE' => 'Tonelada',
        'MTR' => 'Metro',
        'CMT' => 'Centímetro',
        'MMT' => 'Milímetro',
        'MTK' => 'Metro cuadrado',
        'MTQ' => 'Metro cúbico',
        'LTR' => 'Litro',
        'MLT' => 'Mililitro',
        'GLL' => 'Galón',
        'BX' => 'Caja',
        'PK' => 'Paquete',
        'DZN' => 'Docena',
        'SET' => 'Juego',
        'PR' => 'Par',
        'RO' => 'Rollo',
        'BG' => 'Bolsa',
        'BO' => 'Botella',
        'CA' => 'Lata',
        'HUR' => 'Hora',
        'DAY' => 'Día',
        'MON' => 'Mes',
    ];

    /** @var array<string, list<string>> */
    private array $errores = [];

    /**
     * Validaciones sobre los datos de entrada (antes de calcular).
     *
     * @throws ComprobanteInvalido
     */
    public function validarSolicitud(SolicitudComprobante $solicitud, DateTimeImmutable $ahora): void
    {
        $this->errores = [];

        // El cliente y la letra de la serie de una nota siguen las reglas del comprobante que modifica.
        $tipoBase = $solicitud->afectado?->tipo ?? $solicitud->tipo;

        if ($solicitud->tipo->esNota() && $solicitud->afectado === null) {
            $this->error('comprobante', "La {$solicitud->tipo->nombre()} debe indicar la factura o boleta que modifica.");
        }

        $this->validarSerie($solicitud->tipo, $solicitud->serie, $tipoBase);
        $this->validarFecha($solicitud->fechaEmision, $ahora);
        $this->validarCliente($tipoBase, $solicitud->cliente);

        if ($solicitud->afectado !== null && (trim($solicitud->afectado->descripcionMotivo) === '' || mb_strlen($solicitud->afectado->descripcionMotivo) > 250)) {
            $this->error('motivo', 'El motivo de la nota es obligatorio (máximo 250 caracteres).');
        }

        if ($solicitud->moneda !== 'PEN') {
            $this->error('moneda', 'Por ahora solo se emiten comprobantes en soles (PEN).');
        }

        if ($solicitud->observacion !== null && mb_strlen($solicitud->observacion) > 500) {
            $this->error('observacion', 'La observación no puede superar 500 caracteres.');
        }

        $this->validarItems($solicitud->items);

        $this->lanzarSiHayErrores();
    }

    /**
     * Validaciones que dependen de los montos calculados.
     *
     * @throws ComprobanteInvalido
     */
    public function validarCalculo(SolicitudComprobante $solicitud, ResultadoCalculo $calculo): void
    {
        $this->errores = [];
        $total = $calculo->totales->importeTotal;

        if (! Decimal::esPositivo($total)) {
            $this->error('items', 'El importe total del comprobante debe ser mayor a cero.');
        }

        foreach ($calculo->lineas as $linea) {
            if (! Decimal::esPositivo($linea->valorVenta)) {
                $this->error('items.'.($linea->orden - 1), 'El importe de la línea redondea a 0.00; revise cantidad y precio.');
            }
        }

        if (
            $solicitud->tipo === TipoComprobante::Boleta
            && Decimal::comparar($total, self::LIMITE_BOLETA_SIN_IDENTIFICAR) > 0
            && ! $solicitud->cliente->tipoDocumento->identifica()
        ) {
            $this->error('cliente', 'Las boletas mayores a S/ 700.00 deben identificar al cliente (DNI, RUC, CE o pasaporte).');
        }

        if ($d = $solicitud->detraccion) {
            $catalogo = (array) config('facturacion.detracciones', []);
            if ($solicitud->tipo !== TipoComprobante::Factura) {
                $this->error('detraccion', 'La detracción solo va en facturas.');
            } elseif (Decimal::comparar($total, Detraccion::LIMITE) <= 0) {
                $this->error('detraccion', 'La detracción aplica a facturas mayores a S/ 700.00.');
            }
            if (! array_key_exists($d->codigo, $catalogo)) {
                $this->error('detraccion.codigo', 'Código de bien o servicio sujeto a detracción no soportado: '.implode(', ', array_keys($catalogo)).'.');
            }
            if (! Decimal::esPositivo($d->porcentaje) || Decimal::comparar($d->porcentaje, '100') >= 0) {
                $this->error('detraccion.porcentaje', 'Porcentaje de detracción inválido.');
            }
            if (! preg_match('/^[0-9-]{6,30}$/', $d->cuenta)) {
                $this->error('detraccion.cuenta', 'Indique la cuenta de detracciones del Banco de la Nación (solo números).');
            }
        }

        $this->lanzarSiHayErrores();
    }

    /**
     * @param  TipoComprobante  $tipoBase  El propio tipo, o el del comprobante modificado si es una nota.
     */
    private function validarSerie(TipoComprobante $tipo, string $serie, TipoComprobante $tipoBase): void
    {
        $prefijo = $tipoBase->prefijoSerie();

        if (! preg_match("/^{$prefijo}[A-Z0-9]{3}$/", $serie)) {
            $ejemplo = $tipo->serieEjemplo($prefijo);
            $de = $tipo->esNota() ? " de una {$tipoBase->nombre()}" : '';

            $this->error('serie', "La serie de una {$tipo->descripcion()}{$de} debe tener 4 caracteres y empezar con '{$prefijo}' (ej: {$ejemplo}).");
        }
    }

    private function validarFecha(DateTimeImmutable $fecha, DateTimeImmutable $ahora): void
    {
        $zona = new DateTimeZone(self::ZONA_HORARIA);
        $dia = $fecha->setTimezone($zona)->format('Y-m-d');
        $hoy = $ahora->setTimezone($zona)->format('Y-m-d');
        $minimo = $ahora->setTimezone($zona)->modify('-'.self::DIAS_MAXIMOS_ATRASO.' days')->format('Y-m-d');

        if ($dia > $hoy || $fecha > $ahora->modify('+10 minutes')) {
            $this->error('fecha_emision', 'La fecha de emisión no puede ser futura.');
        } elseif ($dia < $minimo) {
            $this->error('fecha_emision', 'La fecha de emisión no puede tener más de '.self::DIAS_MAXIMOS_ATRASO." días de antigüedad (mínimo {$minimo}).");
        }
    }

    private function validarCliente(TipoComprobante $tipo, Cliente $cliente): void
    {
        $numero = $cliente->numeroDocumento;

        if ($tipo === TipoComprobante::Factura && $cliente->tipoDocumento !== TipoDocumentoIdentidad::Ruc) {
            $this->error('cliente.tipo_documento', 'La factura solo puede emitirse a un cliente con RUC (tipo 6).');

            return;
        }

        $valido = match ($cliente->tipoDocumento) {
            TipoDocumentoIdentidad::Ruc => Ruc::esValido($numero),
            TipoDocumentoIdentidad::Dni => (bool) preg_match('/^\d{8}$/', $numero) && $numero !== '00000000',
            TipoDocumentoIdentidad::Varios, TipoDocumentoIdentidad::SinRuc => (bool) preg_match('/^[A-Za-z0-9-]{1,15}$/', $numero),
            TipoDocumentoIdentidad::CarnetExtranjeria, TipoDocumentoIdentidad::Pasaporte => (bool) preg_match('/^[A-Za-z0-9]{1,12}$/', $numero),
            default => (bool) preg_match('/^[A-Za-z0-9-]{1,15}$/', $numero),
        };

        if (! $valido) {
            $mensaje = match ($cliente->tipoDocumento) {
                TipoDocumentoIdentidad::Ruc => "El RUC '{$numero}' no es válido (11 dígitos y dígito verificador correcto).",
                TipoDocumentoIdentidad::Dni => "El DNI '{$numero}' no es válido (8 dígitos).",
                default => "El número de documento '{$numero}' no es válido.",
            };
            $this->error('cliente.numero_documento', $mensaje);
        }

        $razonSocial = trim($cliente->razonSocial);
        if ($razonSocial === '' || mb_strlen($razonSocial) > 500) {
            $this->error('cliente.razon_social', 'La razón social o nombre del cliente es obligatorio (máximo 500 caracteres).');
        }

        if ($cliente->email !== null && filter_var($cliente->email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('cliente.email', 'El correo del cliente no es válido.');
        }
    }

    /**
     * @param  list<ItemSolicitud>  $items
     */
    private function validarItems(array $items): void
    {
        if ($items === []) {
            $this->error('items', 'El comprobante debe tener al menos un ítem.');

            return;
        }

        if (count($items) > self::MAX_ITEMS) {
            $this->error('items', 'El comprobante no puede tener más de '.self::MAX_ITEMS.' ítems.');
        }

        foreach ($items as $i => $item) {
            $campo = "items.{$i}";

            $descripcion = trim($item->descripcion);
            if ($descripcion === '' || mb_strlen($descripcion) > 500) {
                $this->error("{$campo}.descripcion", 'La descripción es obligatoria (máximo 500 caracteres).');
            }

            if (! array_key_exists($item->unidad, self::UNIDADES)) {
                $this->error("{$campo}.unidad", "Unidad de medida '{$item->unidad}' no soportada. Use una de: ".implode(', ', array_keys(self::UNIDADES)).'.');
            }

            if ($item->codigo !== null && ! preg_match('/^[A-Za-z0-9._\-\/]{1,30}$/', $item->codigo)) {
                $this->error("{$campo}.codigo", 'El código del producto admite hasta 30 caracteres alfanuméricos.');
            }

            $this->validarNumeroPositivo("{$campo}.cantidad", $item->cantidad, self::MAX_DECIMALES_CANTIDAD);

            $formasDePrecio = count(array_filter([$item->importe, $item->precioUnitario, $item->valorUnitario], fn ($v) => $v !== null));

            if ($formasDePrecio !== 1) {
                $this->error("{$campo}.precio_unitario", 'Indique solo uno: importe (total de la línea con IGV), precio_unitario (con IGV) o valor_unitario (sin IGV).');
            } elseif ($item->importe !== null) {
                // Es un monto en soles: céntimos como máximo.
                $this->validarNumeroPositivo("{$campo}.importe", $item->importe, 2);
            } elseif ($item->precioUnitario !== null) {
                $this->validarNumeroPositivo("{$campo}.precio_unitario", $item->precioUnitario, self::MAX_DECIMALES_PRECIO);
            } else {
                $this->validarNumeroPositivo("{$campo}.valor_unitario", (string) $item->valorUnitario, self::MAX_DECIMALES_PRECIO);
            }

            if (! in_array($item->afectacion, [ItemSolicitud::GRAVADO, ItemSolicitud::EXONERADO, ItemSolicitud::INAFECTO], true)) {
                $this->error("{$campo}.afectacion_igv", 'Afectación del IGV no soportada. Use 10 (gravado), 20 (exonerado) o 30 (inafecto).');
            } elseif ($item->porcentajeIgv !== null) {
                $tasas = array_map('strval', (array) config('facturacion.tasas_igv', ['18']));
                if (! $item->gravado()) {
                    $this->error("{$campo}.porcentaje_igv", 'Un ítem exonerado o inafecto no lleva tasa de IGV.');
                } elseif (! in_array(Decimal::compactar(Decimal::normalizar($item->porcentajeIgv)), $tasas, true)) {
                    $this->error("{$campo}.porcentaje_igv", 'Tasa de IGV no soportada. Use una de: '.implode(', ', $tasas).'.');
                }
            }
        }
    }

    private function validarNumeroPositivo(string $campo, string $valor, int $maxDecimales): void
    {
        try {
            $numero = Decimal::normalizar($valor);
        } catch (InvalidArgumentException) {
            $this->error($campo, 'Debe ser un número.');

            return;
        }

        if (! Decimal::esPositivo($numero)) {
            $this->error($campo, 'Debe ser mayor a cero.');
        } elseif (Decimal::cantidadDecimales($numero) > $maxDecimales) {
            $this->error($campo, "Admite como máximo {$maxDecimales} decimales.");
        }
    }

    private function error(string $campo, string $mensaje): void
    {
        $this->errores[$campo][] = $mensaje;
    }

    private function lanzarSiHayErrores(): void
    {
        if ($this->errores !== []) {
            throw new ComprobanteInvalido($this->errores);
        }
    }
}
