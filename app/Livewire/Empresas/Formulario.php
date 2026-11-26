<?php

namespace App\Livewire\Empresas;

use App\Facturacion\ConfiguracionEmpresa;
use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Validacion\Ruc;
use App\Facturacion\Core\Validacion\Ubigeo;
use App\Models\Empresa;
use Closure;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

// comentario
class Formulario extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $empresaId = null;

    #[Locked]
    public ?string $empresaUuid = null;

    public string $ruc = '';

    public string $razon_social = '';

    public string $nombre_comercial = '';

    public string $email = '';

    public string $direccion = '';

    public string $ubigeo = '';

    public string $departamento = '';

    public string $provincia = '';

    public string $distrito = '';

    public string $urbanizacion = '';

    public string $codigo_local = '0000';

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public ?string $logoActual = null;

    public bool $quitarLogo = false;

    // Conexión SUNAT (solo al registrar; luego se cambia en la página de la empresa)

    public string $entorno = 'beta';

    /** @var TemporaryUploadedFile|null */
    public $certificado = null;

    public string $clave_certificado = '';

    public string $sol_usuario = '';

    public string $sol_clave = '';

    public function mount(?Empresa $empresa = null): void
    {
        if ($empresa?->exists) {
            Gate::authorize('administrar', $empresa);

            $this->empresaId = $empresa->id;
            $this->empresaUuid = $empresa->uuid;
            $this->fill($empresa->only([
                'ruc', 'razon_social', 'direccion', 'ubigeo', 'departamento', 'provincia', 'distrito', 'codigo_local',
            ]));
            $this->nombre_comercial = (string) $empresa->nombre_comercial;
            $this->email = (string) $empresa->email;
            $this->urbanizacion = (string) $empresa->urbanizacion;
            $this->logoActual = $empresa->logoDataUri();
        } else {
            $this->email = (string) Auth::user()?->email;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $reglas = [
            // El RUC no se edita: los tokens, series y comprobantes cuelgan de él.
            'ruc' => $this->empresaId ? [] : [
                'required', 'digits:11',
                fn (string $attribute, mixed $value, Closure $fail) => Ruc::esValido((string) $value) ?: $fail('El RUC no es válido (revise el dígito verificador).'),
                Rule::unique('empresas', 'ruc'),
            ],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['required', 'string', 'max:255'],
            'ubigeo' => [
                'required', 'digits:6',
                fn (string $attribute, mixed $value, Closure $fail) => Ubigeo::esValido((string) $value)
                    ?: $fail('El ubigeo no es válido: use el código INEI de 6 dígitos de su Ficha RUC (ej. 150122 = Lima, Lima, Miraflores).'),
            ],
            'departamento' => ['required', 'string', 'max:100'],
            'provincia' => ['required', 'string', 'max:100'],
            'distrito' => ['required', 'string', 'max:100'],
            'urbanizacion' => ['nullable', 'string', 'max:100'],
            'codigo_local' => ['required', 'digits:4'],
            'logo' => ConfiguracionEmpresa::reglasArchivos()['logo'],
        ];

        if (! $this->empresaId) {
            $reglas += [
                'entorno' => ['required', Rule::enum(Entorno::class)],
                'certificado' => [
                    Rule::requiredIf($this->entorno === Entorno::Produccion->value),
                    ...ConfiguracionEmpresa::reglasArchivos()['certificado'],
                ],
                'clave_certificado' => ConfiguracionEmpresa::reglasArchivos()['clave_certificado'],
                'sol_usuario' => ['nullable', 'string', 'alpha_num', 'min:3', 'max:20'],
                'sol_clave' => ['nullable', 'string', 'max:50'],
            ];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'ruc.unique' => 'Este RUC ya está registrado por otra cuenta. Si le pertenece, contáctenos.',
            'certificado.required' => 'Para emitir en producción necesita cargar su certificado digital.',
        ] + ConfiguracionEmpresa::mensajes();
    }

    public function guardar(ConfiguracionEmpresa $configuracion): void
    {
        $datos = $this->validate();

        $fiscales = Arr::only($datos, [
            'ruc', 'razon_social', 'nombre_comercial', 'email', 'direccion', 'ubigeo',
            'departamento', 'provincia', 'distrito', 'urbanizacion', 'codigo_local',
        ]);

        foreach (['razon_social', 'nombre_comercial', 'direccion', 'departamento', 'provincia', 'distrito', 'urbanizacion'] as $campo) {
            $fiscales[$campo] = mb_strtoupper(trim((string) $fiscales[$campo])) ?: null;
        }
        $fiscales['email'] = $fiscales['email'] ?: null;

        if ($this->empresaId) {
            $empresa = Empresa::findOrFail($this->empresaId);
            Gate::authorize('administrar', $empresa);
            unset($fiscales['ruc']);
            $empresa->update($fiscales);
            $this->guardarLogo($configuracion, $empresa);

            Flux::toast(variant: 'success', text: 'Datos de la empresa actualizados.');
        } else {
            // Todo o nada: si el certificado o la clave SOL fallan, la empresa no queda registrada a medias.
            $empresa = DB::transaction(function () use ($fiscales, $configuracion) {
                $empresa = Empresa::create($fiscales + ['user_id' => Auth::id(), 'entorno' => Entorno::Beta]);

                if ($this->certificado) {
                    $contenido = (string) file_get_contents($this->certificado->getRealPath());
                    $configuracion->certificado($empresa, $contenido, $this->clave_certificado);
                }

                $configuracion->conexion($empresa, Entorno::from($this->entorno), $this->sol_usuario, $this->sol_clave);
                $this->guardarLogo($configuracion, $empresa);

                return $empresa;
            });

            $this->certificado?->delete();

            Flux::toast(variant: 'success', text: 'Empresa registrada. Ahora registre sus series y conecte sus sistemas.');
        }

        $this->redirectRoute('empresas.show', ['empresa' => $empresa->uuid], navigate: true);
    }

    private function guardarLogo(ConfiguracionEmpresa $configuracion, Empresa $empresa): void
    {
        if ($this->logo) {
            $configuracion->logo($empresa, $this->logo);
            $this->logo->delete();
        } elseif ($this->quitarLogo) {
            $configuracion->quitarLogo($empresa);
        }
    }

    public function render(): mixed
    {
        return view('livewire.empresas.formulario')
            ->title($this->empresaId ? 'Editar empresa' : 'Registrar empresa');
    }
}
