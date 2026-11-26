<?php

namespace App\Facturacion;

use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\Core\Enums\TipoDocumentoIdentidad;
use App\Facturacion\Core\Sunat\Entorno;
use App\Models\Comprobante;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Dompdf\Options;
use InvalidArgumentException;

/**
 * PDF de la factura/boleta (representación impresa): A4 o ticket de 80 mm.
 * Se genera al vuelo desde los datos guardados; no se almacena.
 */
class RepresentacionImpresa
{
    public const FORMATOS = ['a4', 'ticket'];

    /** 80 mm en puntos PDF. */
    private const ANCHO_TICKET = 226.77;

    public function pdf(Comprobante $comprobante, string $formato = 'a4'): string
    {
        if (! in_array($formato, self::FORMATOS, true)) {
            throw new InvalidArgumentException("Formato de impresión no soportado: {$formato}");
        }

        $html = view("pdf.{$formato}", $this->datos($comprobante))->render();

        if ($formato === 'a4') {
            return $this->renderizar($html, 'A4');
        }

        // Ticket: primero se mide el alto real del contenido y luego se genera a esa medida (sin papel en blanco).
        $alto = 0.0;
        $this->renderizar($html, [0, 0, self::ANCHO_TICKET, 5000], function (Frame $frame) use (&$alto) {
            $nodo = $frame->get_node();
            if ($nodo instanceof \DOMElement && $nodo->getAttribute('id') === 'fin-ticket') {
                $caja = $frame->get_padding_box();
                $alto = $caja['y'] + $caja['h'];
            }
        });

        return $this->renderizar($html, [0, 0, self::ANCHO_TICKET, max($alto + 12, 200)]);
    }

    public function nombreArchivo(Comprobante $comprobante, string $formato): string
    {
        return $comprobante->nombreArchivo().($formato === 'ticket' ? '-ticket' : '').'.pdf';
    }

    /**
     * Texto del QR según SUNAT: RUC|TIPO|SERIE|NUMERO|IGV|TOTAL|FECHA|TIPO DOC CLIENTE|NUM DOC CLIENTE|HASH|
     */
    public function textoQr(Comprobante $comprobante): string
    {
        return implode('|', [
            $comprobante->empresa->ruc,
            $comprobante->tipo_comprobante->value,
            $comprobante->serie,
            $comprobante->correlativo,
            $comprobante->igv,
            $comprobante->total,
            $comprobante->fecha_emision->setTimezone('America/Lima')->format('Y-m-d'),
            $comprobante->cliente_tipo_documento,
            $comprobante->cliente_numero_documento,
            $comprobante->hash,
        ]).'|';
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(Comprobante $comprobante): array
    {
        $comprobante->loadMissing('empresa', 'items', 'afectado');
        $empresa = $comprobante->empresa;

        $qr = (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'scale' => 6,
            'outputBase64' => true,
        ])))->render($this->textoQr($comprobante));

        $tipoDoc = TipoDocumentoIdentidad::tryFrom($comprobante->cliente_tipo_documento);

        return [
            'c' => $comprobante,
            'empresa' => $empresa,
            'logo' => $empresa->logoDataUri(),
            'qr' => $qr,
            'titulo' => $comprobante->tipo_comprobante->descripcion(),
            'fecha' => $comprobante->fecha_emision->setTimezone('America/Lima'),
            'documentoCliente' => match ($tipoDoc) {
                TipoDocumentoIdentidad::Ruc => 'RUC',
                TipoDocumentoIdentidad::Dni => 'DNI',
                TipoDocumentoIdentidad::CarnetExtranjeria => 'CE',
                TipoDocumentoIdentidad::Pasaporte => 'PASAPORTE',
                TipoDocumentoIdentidad::Varios, TipoDocumentoIdentidad::SinRuc, null => null,
                default => 'DOC.',
            },
            'marcaAgua' => match (true) {
                $comprobante->estado === EstadoComprobante::Rechazado => 'RECHAZADO POR SUNAT',
                $comprobante->entorno === Entorno::Beta => 'PRUEBA - SIN VALOR',
                default => null,
            },
        ];
    }

    /**
     * @param  string|array<int, float|int>  $papel
     */
    private function renderizar(string $html, string|array $papel, ?callable $alTerminarFrame = null): string
    {
        $opciones = new Options;
        $opciones->setDefaultFont('DejaVu Sans');
        $opciones->setIsRemoteEnabled(false); // Imágenes solo como data URI: nada se descarga de internet.
        $opciones->setChroot(resource_path('views/pdf'));

        $dompdf = new Dompdf($opciones);
        $dompdf->setPaper($papel);
        $dompdf->loadHtml($html, 'UTF-8');

        if ($alTerminarFrame) {
            $dompdf->setCallbacks([['event' => 'end_frame', 'f' => $alTerminarFrame]]);
        }

        $dompdf->render();

        return (string) $dompdf->output();
    }
}
