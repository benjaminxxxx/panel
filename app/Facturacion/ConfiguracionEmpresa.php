<?php

namespace App\Facturacion;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Certificado\CertificadoInvalido;
use App\Facturacion\Core\Sunat\Entorno;
use App\Models\Empresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Reglas para configurar certificado, entorno, clave SOL y logo de una empresa.
 * Las usan el registro, la página de la empresa y los comandos: un solo lugar para las reglas.
 *
 * Los errores se lanzan como ValidationException con los mismos nombres de campo que los formularios.
 */
class ConfiguracionEmpresa
{
    /**
     * @throws ValidationException
     */
    public function certificado(Empresa $empresa, string $contenido, ?string $clave): CertificadoDigital
    {
        try {
            $certificado = CertificadoDigital::cargar($contenido, $clave ?: null);
        } catch (CertificadoInvalido $e) {
            throw ValidationException::withMessages(['certificado' => $e->getMessage()]);
        }

        if (! $certificado->estaVigente(now()->toDateTimeImmutable())) {
            throw ValidationException::withMessages(['certificado' => "El certificado no está vigente (válido del {$certificado->validoDesde->format('d/m/Y')} al {$certificado->validoHasta->format('d/m/Y')})."]);
        }

        $rucCertificado = $certificado->rucEnSujeto();
        if ($rucCertificado !== null && $rucCertificado !== $empresa->ruc) {
            throw ValidationException::withMessages(['certificado' => "El certificado pertenece al RUC {$rucCertificado}, no a {$empresa->ruc}."]);
        }

        $empresa->guardarCertificado($certificado);

        return $certificado;
    }

    /**
     * @param  string|null  $solClave  null o vacío = conservar la guardada.
     *
     * @throws ValidationException
     */
    public function conexion(Empresa $empresa, Entorno $entorno, ?string $solUsuario, ?string $solClave): void
    {
        $usuario = mb_strtoupper(trim((string) $solUsuario)) ?: null;
        $clave = filled($solClave) ? $solClave : $empresa->sol_clave;

        if ($entorno === Entorno::Produccion) {
            if (! $empresa->certificado_vence_en?->isFuture()) {
                throw ValidationException::withMessages(['entorno' => 'Para emitir en producción primero cargue un certificado digital vigente.']);
            }
            if (! $usuario || ! $clave) {
                throw ValidationException::withMessages(['sol_usuario' => 'En producción se requieren el usuario y la clave SOL.']);
            }
        }

        $empresa->update(['entorno' => $entorno, 'sol_usuario' => $usuario, 'sol_clave' => $clave]);
    }

    public function logo(Empresa $empresa, UploadedFile $archivo): void
    {
        $anterior = $empresa->logo_path;
        $extension = strtolower($archivo->extension() ?: 'png');
        $ruta = config('facturacion.directorio')."/{$empresa->ruc}/logo-".now()->format('YmdHis').".{$extension}";

        $empresa->disco()->put($ruta, (string) file_get_contents($archivo->getRealPath()));
        $empresa->update(['logo_path' => $ruta]);

        if ($anterior && $anterior !== $ruta) {
            $empresa->disco()->delete($anterior);
        }
    }

    public function quitarLogo(Empresa $empresa): void
    {
        if ($empresa->logo_path) {
            $empresa->disco()->delete($empresa->logo_path);
            $empresa->update(['logo_path' => null]);
        }
    }

    /**
     * Reglas de validación compartidas para los campos de archivo.
     *
     * @return array<string, list<string>>
     */
    public static function reglasArchivos(): array
    {
        return [
            'certificado' => ['nullable', 'file', 'max:100', 'extensions:pfx,p12,pem'],
            'clave_certificado' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'certificado.extensions' => 'El certificado debe ser .pfx, .p12 o .pem.',
            'logo.image' => 'El logo debe ser una imagen PNG o JPG.',
            'logo.mimes' => 'El logo debe ser una imagen PNG o JPG.',
            'logo.dimensions' => 'El logo no puede superar 2000 x 2000 píxeles.',
            'sol_usuario.alpha_num' => 'El usuario SOL solo admite letras y números (sin el RUC).',
        ];
    }
}
