<?php

namespace App\Livewire\Empresas;

use App\Facturacion\ConfiguracionEmpresa;
use App\Facturacion\Core\Sunat\Entorno;
use App\Models\Empresa;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ConexionSunat extends Component
{
    use WithFileUploads;

    public Empresa $empresa;

    /** @var TemporaryUploadedFile|null */
    public $certificado = null;

    public string $clave_certificado = '';

    public string $sol_usuario = '';

    public string $sol_clave = '';

    public string $entorno = 'beta';

    public function mount(Empresa $empresa): void
    {
        Gate::authorize('administrar', $empresa);

        $this->empresa = $empresa;
        $this->sol_usuario = (string) $empresa->sol_usuario;
        $this->entorno = $empresa->entorno->value;
    }

    public function subirCertificado(ConfiguracionEmpresa $configuracion): void
    {
        Gate::authorize('administrar', $this->empresa);

        $reglas = ConfiguracionEmpresa::reglasArchivos();
        $this->validate(
            ['certificado' => ['required', ...$reglas['certificado']], 'clave_certificado' => $reglas['clave_certificado']],
            ['certificado.required' => 'Seleccione el archivo del certificado.'] + ConfiguracionEmpresa::mensajes(),
        );

        $contenido = (string) file_get_contents($this->certificado->getRealPath());
        // El temporal no debe quedar en el servidor: solo se guarda el certificado cifrado.
        $this->certificado->delete();
        $clave = $this->clave_certificado;
        $this->reset('certificado', 'clave_certificado');

        $certificado = $configuracion->certificado($this->empresa, $contenido, $clave);

        Flux::toast(variant: 'success', text: "Certificado cargado. Vence el {$certificado->validoHasta->format('d/m/Y')}.");
        $this->dispatch('empresa-actualizada');
    }

    public function guardarSunat(ConfiguracionEmpresa $configuracion): void
    {
        Gate::authorize('administrar', $this->empresa);

        $this->validate([
            'entorno' => ['required', Rule::enum(Entorno::class)],
            'sol_usuario' => ['nullable', 'string', 'alpha_num', 'min:3', 'max:20'],
            'sol_clave' => ['nullable', 'string', 'max:50'],
        ], ConfiguracionEmpresa::mensajes());

        $configuracion->conexion($this->empresa, Entorno::from($this->entorno), $this->sol_usuario, $this->sol_clave);

        $this->sol_clave = '';
        $this->sol_usuario = (string) $this->empresa->sol_usuario;

        Flux::toast(variant: 'success', text: 'Conexión con SUNAT actualizada.');
        $this->dispatch('empresa-actualizada');
    }
}
