<?php

namespace App\Providers;

use App\Facturacion\Core\Sunat\Greenter\GreenterServicioSunat;
use App\Facturacion\Core\Sunat\ServicioSunat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ServicioSunat::class, fn () => new GreenterServicioSunat(config('facturacion.cache_xml')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        JsonResource::withoutWrapping();

        // Límite por token de API (cada sistema/empresa tiene su propia cuota).
        RateLimiter::for('facturacion', fn (Request $request) => Limit::perMinute(120)
            ->by((string) $request->bearerToken() ?: (string) $request->ip()));

        // Consultas de RUC/DNI: cada una va a SUNAT; una cuota por clave evita que un local sature.
        RateLimiter::for('consultas', fn (Request $request) => Limit::perMinute(30)
            ->by((string) $request->bearerToken() ?: (string) $request->ip()));

        // El proveedor administra los locales, las versiones y el catálogo; el resto solo su facturación.
        Gate::define('proveedor', fn (User $user): bool => (bool) $user->es_admin);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
