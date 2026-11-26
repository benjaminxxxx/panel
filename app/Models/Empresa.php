<?php

namespace App\Models;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Dominio\Emisor;
use App\Facturacion\Core\Sunat\Credenciales;
use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\EmpresaNoHabilitada;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Empresa emisora (un RUC). Sus secretos se guardan cifrados con APP_KEY.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $ruc
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string|null $email
 * @property string|null $logo_path
 * @property string $direccion
 * @property string $ubigeo
 * @property string $departamento
 * @property string $provincia
 * @property string $distrito
 * @property string|null $urbanizacion
 * @property string $codigo_local
 * @property Entorno $entorno
 * @property string|null $sol_usuario
 * @property string|null $sol_clave
 * @property string|null $certificado_pem
 * @property Carbon|null $certificado_vence_en
 * @property string|null $certificado_sujeto
 * @property bool $activo
 */
#[Fillable([
    'user_id', 'ruc', 'razon_social', 'nombre_comercial', 'email', 'logo_path',
    'direccion', 'ubigeo', 'departamento', 'provincia', 'distrito', 'urbanizacion', 'codigo_local',
    'entorno', 'sol_usuario', 'sol_clave', 'activo',
])]
#[Hidden(['sol_clave', 'certificado_pem'])]
class Empresa extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Empresa $empresa) {
            $empresa->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'entorno' => Entorno::class,
            'sol_clave' => 'encrypted',
            'certificado_pem' => 'encrypted',
            'certificado_vence_en' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EmpresaToken, $this> */
    public function tokens(): HasMany
    {
        return $this->hasMany(EmpresaToken::class);
    }

    /** @return HasMany<Serie, $this> */
    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    /** @return HasMany<Comprobante, $this> */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    /**
     * Disco privado donde viven logo, XML y CDR.
     */
    public function disco(): Filesystem
    {
        return Storage::disk(config('facturacion.disco'));
    }

    /**
     * Logo como data URI: sirve para el PDF y para la vista previa sin exponer el archivo públicamente.
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! $this->disco()->exists($this->logo_path)) {
            return null;
        }

        $mime = str_ends_with(strtolower($this->logo_path), '.png') ? 'image/png' : 'image/jpeg';

        return "data:{$mime};base64,".base64_encode((string) $this->disco()->get($this->logo_path));
    }

    public function guardarCertificado(CertificadoDigital $certificado): void
    {
        $this->certificado_pem = $certificado->pem();
        $this->certificado_vence_en = Carbon::instance($certificado->validoHasta);
        $this->certificado_sujeto = Str::limit($certificado->sujeto, 250);
        $this->save();
    }

    public function emisor(): Emisor
    {
        return new Emisor(
            ruc: $this->ruc,
            razonSocial: $this->razon_social,
            nombreComercial: $this->nombre_comercial,
            direccion: $this->direccion,
            ubigeo: $this->ubigeo,
            departamento: $this->departamento,
            provincia: $this->provincia,
            distrito: $this->distrito,
            urbanizacion: $this->urbanizacion,
            codigoLocal: $this->codigo_local,
        );
    }

    /**
     * @throws EmpresaNoHabilitada
     */
    public function credenciales(): Credenciales
    {
        if (! $this->activo) {
            throw new EmpresaNoHabilitada('La empresa está desactivada.');
        }

        if (! $this->certificado_pem) {
            throw new EmpresaNoHabilitada('La empresa no tiene certificado digital cargado.');
        }

        $certificado = CertificadoDigital::cargar($this->certificado_pem);

        if (! $certificado->estaVigente(now()->toDateTimeImmutable())) {
            throw new EmpresaNoHabilitada("El certificado digital venció el {$certificado->validoHasta->format('d/m/Y')}.");
        }

        $esBeta = $this->entorno === Entorno::Beta;

        if (! $esBeta && (! $this->sol_usuario || ! $this->sol_clave)) {
            throw new EmpresaNoHabilitada('Faltan el usuario y la clave SOL para emitir en producción.');
        }

        return new Credenciales(
            ruc: $this->ruc,
            usuarioSol: $esBeta ? Credenciales::USUARIO_BETA : (string) $this->sol_usuario,
            claveSol: $esBeta ? Credenciales::CLAVE_BETA : (string) $this->sol_clave,
            certificado: $certificado,
            entorno: $this->entorno,
        );
    }
}
