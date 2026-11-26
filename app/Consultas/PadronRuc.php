<?php

namespace App\Consultas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * Padrón reducido del RUC de SUNAT (padron_reducido_ruc.zip, publicado para descarga libre).
 *
 * Formato: texto Latin-1 separado por "|", una fila por contribuyente:
 * RUC|RAZÓN SOCIAL|ESTADO|CONDICIÓN|UBIGEO|TIPO VÍA|NOMBRE VÍA|CÓD. ZONA|TIPO ZONA|NÚMERO|INTERIOR|LOTE|DPTO|MANZANA|KM|
 *
 * Se carga en una tabla nueva y al final se intercambia con la vigente: las consultas nunca
 * ven el padrón a medio cargar.
 */
class PadronRuc
{
    public const URL = 'https://www2.sunat.gob.pe/padron_reducido_ruc.zip';

    private const LOTE = 2000;

    /** @return array<string, string>|null */
    public function buscar(string $ruc): ?array
    {
        $fila = DB::table('padron_ruc')->where('ruc', $ruc)->first();

        return $fila ? (array) $fila : null;
    }

    public function tieneDatos(): bool
    {
        return DB::table('padron_ruc')->exists();
    }

    /**
     * Importa el padrón desde un .zip o .txt local. Devuelve cuántos contribuyentes cargó.
     *
     * @param  callable(int): void|null  $progreso
     */
    public function importar(string $archivo, ?callable $progreso = null): int
    {
        $flujo = $this->abrir($archivo);
        fgets($flujo); // encabezados

        Schema::dropIfExists('padron_ruc_nuevo');
        DB::statement('CREATE TABLE padron_ruc_nuevo AS SELECT * FROM padron_ruc WHERE 1 = 0');
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE padron_ruc_nuevo ADD PRIMARY KEY (ruc)');
        }

        $total = 0;
        $lote = [];
        while (($linea = fgets($flujo)) !== false) {
            $fila = $this->fila($linea);
            if ($fila === null) {
                continue;
            }
            $lote[] = $fila;
            if (count($lote) >= self::LOTE) {
                $total += $this->guardar($lote);
                $lote = [];
                if ($progreso) {
                    $progreso($total);
                }
            }
        }
        $total += $this->guardar($lote);
        fclose($flujo);

        if ($total === 0) {
            Schema::dropIfExists('padron_ruc_nuevo');
            throw new RuntimeException('El archivo no tiene contribuyentes: no se reemplazó el padrón vigente.');
        }
        Schema::rename('padron_ruc', 'padron_ruc_viejo');
        Schema::rename('padron_ruc_nuevo', 'padron_ruc');
        Schema::drop('padron_ruc_viejo');

        return $total;
    }

    /** @return array<string, string|null>|null */
    public function fila(string $linea): ?array
    {
        if (! mb_check_encoding($linea, 'UTF-8')) {
            $linea = mb_convert_encoding($linea, 'UTF-8', 'ISO-8859-1');
        }
        $c = array_map('trim', explode('|', rtrim($linea, "\r\n")));
        if (count($c) < 5 || ! preg_match('/^\d{11}$/', $c[0])) {
            return null;
        }
        $limpiar = fn (?string $v) => ($v === null || $v === '-' || $v === '----') ? '' : $v;
        // Tipo y nombre de vía, número, interior, zona, manzana, lote, km: como SUNAT lo muestra.
        $partes = array_filter([
            trim($limpiar($c[5] ?? '').' '.$limpiar($c[6] ?? '')),
            $limpiar($c[9] ?? '') !== '' ? 'NRO. '.$c[9] : '',
            $limpiar($c[10] ?? '') !== '' ? 'INT. '.$c[10] : '',
            $limpiar($c[13] ?? '') !== '' ? 'MZA. '.$c[13] : '',
            $limpiar($c[11] ?? '') !== '' ? 'LOTE. '.$c[11] : '',
            $limpiar($c[12] ?? '') !== '' ? 'DPTO. '.$c[12] : '',
            $limpiar($c[14] ?? '') !== '' ? 'KM. '.$c[14] : '',
            trim($limpiar($c[8] ?? '').' '.$limpiar($c[7] ?? '')),
        ]);

        return [
            'ruc' => $c[0],
            'razon_social' => mb_substr($c[1], 0, 250),
            'estado' => mb_substr($c[2], 0, 40),
            'condicion' => mb_substr($c[3], 0, 40),
            'ubigeo' => preg_match('/^\d{6}$/', $c[4]) ? $c[4] : null,
            'direccion' => mb_substr(implode(' ', $partes), 0, 300),
        ];
    }

    /** @param list<array<string, string|null>> $filas */
    private function guardar(array $filas): int
    {
        if ($filas === []) {
            return 0;
        }

        return DB::table('padron_ruc_nuevo')->insertOrIgnore($filas);
    }

    /** @return resource */
    private function abrir(string $archivo)
    {
        if (str_ends_with(strtolower($archivo), '.zip')) {
            $zip = new ZipArchive;
            if ($zip->open($archivo) !== true || $zip->numFiles < 1) {
                throw new RuntimeException("No se pudo abrir {$archivo}");
            }
            $nombre = (string) $zip->getNameIndex(0);
            $zip->close();
            $flujo = fopen('zip://'.$archivo.'#'.$nombre, 'r');
        } else {
            $flujo = fopen($archivo, 'r');
        }
        if ($flujo === false) {
            throw new RuntimeException("No se pudo leer {$archivo}");
        }

        return $flujo;
    }
}
