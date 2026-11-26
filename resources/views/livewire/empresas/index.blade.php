<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Mis empresas</flux:heading>
            <flux:subheading>Configure su facturación una sola vez y conecte todos sus sistemas (POS, alquiler, restaurante, hotel).</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('empresas.create')" wire:navigate data-test="registrar-empresa">
            Registrar empresa
        </flux:button>
    </div>

    @if ($this->empresas->isEmpty())
        <flux:card class="space-y-2 text-center">
            <flux:heading>Aún no registra ninguna empresa</flux:heading>
            <flux:text>Tenga a la mano su Ficha RUC y su certificado digital (.pfx) para empezar.</flux:text>
        </flux:card>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($this->empresas as $empresa)
                <a href="{{ route('empresas.show', $empresa) }}" wire:navigate wire:key="empresa-{{ $empresa->id }}"
                   class="block rounded-xl border border-zinc-200 p-5 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <flux:heading>{{ $empresa->razon_social }}</flux:heading>
                            <flux:text size="sm">RUC {{ $empresa->ruc }}</flux:text>
                        </div>
                        @if ($empresa->entorno === \App\Facturacion\Core\Sunat\Entorno::Produccion)
                            <flux:badge color="green" size="sm">Producción</flux:badge>
                        @else
                            <flux:badge color="amber" size="sm">Modo prueba</flux:badge>
                        @endif
                    </div>
                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                        <span>{{ $empresa->comprobantes_count }} comprobantes</span>
                        <span>{{ $empresa->tokens_count }} sistemas conectados</span>
                        <span>
                            @if ($empresa->certificado_vence_en)
                                Certificado hasta {{ $empresa->certificado_vence_en->format('d/m/Y') }}
                            @else
                                <span class="text-amber-600">Sin certificado</span>
                            @endif
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
