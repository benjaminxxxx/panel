<?php

namespace App\Console\Commands\Facturacion;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Certificado\GeneradorCertificadoPrueba;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Sunat\Entorno;
use App\Models\Empresa;
use App\Models\EmpresaToken;
use App\Models\Serie;
use Illuminate\Console\Command;

class DemoCommand extends Command
{
    private const RUC_BETA = '20000000001';

    protected $signature = 'facturacion:demo {sistema=pos}';

    protected $description = 'Crea una empresa de pruebas para SUNAT beta (RUC 20000000001) con certificado autofirmado, series F001/B001 y un token';

    public function handle(): int
    {
        $empresa = Empresa::updateOrCreate(['ruc' => self::RUC_BETA], [
            'razon_social' => 'EMPRESA DE PRUEBAS BETA S.A.C.',
            'nombre_comercial' => 'KIPUSOFT DEMO',
            'direccion' => 'AV. LOS OLIVOS 123',
            'ubigeo' => '150101',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'LIMA',
            'entorno' => Entorno::Beta,
            'activo' => true,
        ]);

        if (! $empresa->certificado_pem) {
            $empresa->guardarCertificado(CertificadoDigital::cargar(GeneradorCertificadoPrueba::generar(self::RUC_BETA)['pem']));
        }

        foreach ([[TipoComprobante::Factura, 'F001'], [TipoComprobante::Boleta, 'B001']] as [$tipo, $serie]) {
            Serie::firstOrCreate(['empresa_id' => $empresa->id, 'tipo_comprobante' => $tipo, 'serie' => $serie]);
        }

        [, $token] = EmpresaToken::generar($empresa, (string) $this->argument('sistema'), 'demo');

        $this->info('Empresa demo lista en SUNAT beta. Token:');
        $this->line($token);

        return self::SUCCESS;
    }
}
