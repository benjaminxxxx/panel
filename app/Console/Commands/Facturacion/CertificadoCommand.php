<?php

namespace App\Console\Commands\Facturacion;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Certificado\CertificadoInvalido;
use App\Models\Empresa;
use Illuminate\Console\Command;

class CertificadoCommand extends Command
{
    protected $signature = 'facturacion:certificado
        {ruc : RUC de la empresa}
        {archivo : Ruta al .pfx/.p12 o .pem}
        {--clave= : Contraseña del .pfx}';

    protected $description = 'Carga (cifrado) el certificado digital de una empresa';

    public function handle(): int
    {
        $empresa = Empresa::where('ruc', $this->argument('ruc'))->first();
        if (! $empresa) {
            $this->error('Empresa no encontrada.');

            return self::FAILURE;
        }

        $archivo = (string) $this->argument('archivo');
        if (! is_readable($archivo)) {
            $this->error("No se puede leer el archivo {$archivo}");

            return self::FAILURE;
        }

        $clave = $this->option('clave');
        if ($clave === null && ! str_contains((string) file_get_contents($archivo), '-----BEGIN')) {
            $clave = (string) $this->secret('Contraseña del certificado');
        }

        try {
            $certificado = CertificadoDigital::cargar((string) file_get_contents($archivo), $clave);
        } catch (CertificadoInvalido $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $ahora = now()->toDateTimeImmutable();
        if (! $certificado->estaVigente($ahora)) {
            $this->error("El certificado no está vigente (válido del {$certificado->validoDesde->format('d/m/Y')} al {$certificado->validoHasta->format('d/m/Y')}).");

            return self::FAILURE;
        }

        $rucCertificado = $certificado->rucEnSujeto();
        if ($rucCertificado && $rucCertificado !== $empresa->ruc) {
            $this->warn("Atención: el certificado parece pertenecer al RUC {$rucCertificado}, no a {$empresa->ruc}.");
        }

        $empresa->guardarCertificado($certificado);

        $this->info("Certificado cargado. Sujeto: {$certificado->sujeto}");
        $this->info("Vence: {$certificado->validoHasta->format('d/m/Y')} ({$certificado->diasParaVencer($ahora)} días)");

        return self::SUCCESS;
    }
}
