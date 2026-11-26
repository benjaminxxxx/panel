<?php

namespace App\Console\Commands\Facturacion;

use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Validacion\Ruc;
use App\Facturacion\Core\Validacion\Ubigeo;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Console\Command;

class EmpresaCommand extends Command
{
    protected $signature = 'facturacion:empresa
        {ruc : RUC del emisor}
        {--razon-social= : Razón social}
        {--nombre-comercial= : Nombre comercial}
        {--direccion= : Dirección fiscal}
        {--ubigeo= : Ubigeo INEI de 6 dígitos}
        {--departamento=}
        {--provincia=}
        {--distrito=}
        {--urbanizacion=}
        {--email= : Correo de contacto}
        {--usuario= : Email del usuario dueño (cuenta del panel)}
        {--entorno=beta : beta | produccion}
        {--sol-usuario= : Usuario SOL secundario}
        {--sol-clave= : Clave SOL}';

    protected $description = 'Registra o actualiza una empresa emisora';

    public function handle(): int
    {
        $ruc = (string) $this->argument('ruc');
        if (! Ruc::esValido($ruc)) {
            $this->error("RUC inválido: {$ruc}");

            return self::FAILURE;
        }

        $entorno = Entorno::tryFrom((string) $this->option('entorno'));
        if (! $entorno) {
            $this->error('El entorno debe ser beta o produccion.');

            return self::FAILURE;
        }

        $empresa = Empresa::firstOrNew(['ruc' => $ruc]);

        $datos = array_filter([
            'razon_social' => $this->option('razon-social'),
            'nombre_comercial' => $this->option('nombre-comercial'),
            'direccion' => $this->option('direccion'),
            'ubigeo' => $this->option('ubigeo'),
            'departamento' => $this->option('departamento'),
            'provincia' => $this->option('provincia'),
            'distrito' => $this->option('distrito'),
            'urbanizacion' => $this->option('urbanizacion'),
            'email' => $this->option('email'),
            'sol_usuario' => $this->option('sol-usuario'),
            'sol_clave' => $this->option('sol-clave'),
        ], fn ($v) => $v !== null && $v !== '');

        if ($email = $this->option('usuario')) {
            $usuario = User::where('email', $email)->first();
            if (! $usuario) {
                $this->error("No existe un usuario con email {$email}.");

                return self::FAILURE;
            }
            $datos['user_id'] = $usuario->id;
        }

        $empresa->fill($datos + ['entorno' => $entorno]);

        $faltantes = array_filter(['razon_social', 'direccion', 'ubigeo', 'departamento', 'provincia', 'distrito'], fn ($c) => blank($empresa->{$c}));
        if ($faltantes) {
            $this->error('Faltan datos obligatorios: --'.implode(', --', str_replace('_', '-', $faltantes)));

            return self::FAILURE;
        }

        if (! Ubigeo::esValido($empresa->ubigeo)) {
            $this->error('El ubigeo no es válido: use el código INEI de 6 dígitos (ej. 150101).');

            return self::FAILURE;
        }

        $empresa->save();

        $this->info(($empresa->wasRecentlyCreated ? 'Empresa registrada' : 'Empresa actualizada').": {$empresa->ruc} {$empresa->razon_social} [{$empresa->entorno->value}]");

        return self::SUCCESS;
    }
}
