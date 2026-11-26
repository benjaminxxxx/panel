<section class="mx-auto w-full max-w-5xl space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            @if ($logo = $empresa->logoDataUri())
                <img src="{{ $logo }}" alt="Logo" class="size-16 rounded-lg border border-zinc-200 bg-white object-contain p-1 dark:border-zinc-700">
            @endif
            <div>
            <flux:heading size="xl" level="1">{{ $empresa->razon_social }}</flux:heading>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <flux:text>RUC {{ $empresa->ruc }}</flux:text>
                @if ($empresa->entorno === \App\Facturacion\Core\Sunat\Entorno::Produccion)
                    <flux:badge color="green" size="sm">Producción</flux:badge>
                @else
                    <flux:badge color="amber" size="sm">Modo prueba (beta)</flux:badge>
                @endif
            </div>
            </div>
        </div>
        <flux:button icon="pencil-square" :href="route('empresas.edit', $empresa)" wire:navigate>Editar datos</flux:button>
    </div>

    <flux:card class="space-y-3">
        <flux:heading size="lg">Configuración</flux:heading>
        <ol class="space-y-2">
            @foreach ($this->pasos as $i => $paso)
                <li class="flex items-start gap-3" data-test="paso-{{ $i }}">
                    @if ($paso['listo'])
                        <flux:icon.check-circle variant="solid" class="size-5 shrink-0 text-green-600" />
                    @else
                        <flux:icon.exclamation-circle variant="solid" class="size-5 shrink-0 text-amber-500" />
                    @endif
                    <div>
                        <span class="font-medium">{{ $paso['titulo'] }}</span>
                        <flux:text size="sm">{{ $paso['detalle'] }}</flux:text>
                    </div>
                </li>
            @endforeach
        </ol>
    </flux:card>

    <livewire:empresas.conexion-sunat :empresa="$empresa" :key="'conexion-'.$empresa->id" />
    <livewire:empresas.series :empresa="$empresa" :key="'series-'.$empresa->id" />
    <livewire:empresas.tokens :empresa="$empresa" :key="'tokens-'.$empresa->id" />
    <livewire:empresas.comprobantes :empresa="$empresa" :key="'comprobantes-'.$empresa->id" />
</section>
