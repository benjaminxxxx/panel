<flux:card class="space-y-6">
    <div>
        <flux:heading size="lg">Sistemas conectados</flux:heading>
        <flux:text>
            Genere un token para cada sistema que emitirá comprobantes con esta empresa y péguelo en su configuración.
            URL de la API: <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">{{ url('/api/v1') }}</code>
        </flux:text>
    </div>

    @if ($this->tokens->isNotEmpty())
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Sistema</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Creado</flux:table.column>
                <flux:table.column>Último uso</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->tokens as $token)
                    <flux:table.row :key="$token->id">
                        <flux:table.cell class="font-medium">{{ config("facturacion.sistemas.{$token->sistema}", $token->sistema) }}</flux:table.cell>
                        <flux:table.cell>{{ $token->nombre }}</flux:table.cell>
                        <flux:table.cell>{{ $token->created_at?->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $token->ultimo_uso_at?->diffForHumans() ?? 'Nunca' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($token->revocado_at)
                                <flux:badge size="sm" color="zinc">Revocado</flux:badge>
                            @else
                                <flux:button size="sm" variant="ghost" icon="no-symbol"
                                             wire:click="revocar({{ $token->id }})"
                                             wire:confirm="¿Revocar este token? El sistema que lo usa dejará de poder emitir.">
                                    Revocar
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <form wire:submit="crear" class="grid items-start gap-4 sm:grid-cols-3">
        <flux:select wire:model="sistema" label="Sistema">
            @foreach (config('facturacion.sistemas') as $clave => $etiqueta)
                <flux:select.option :value="$clave">{{ $etiqueta }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model="nombre" label="Nombre (opcional)" placeholder="POS tienda centro" />
        <div class="sm:pt-6">
            <flux:button type="submit" variant="primary" class="w-full" data-test="crear-token">Generar token</flux:button>
        </div>
    </form>

    <flux:modal name="token-nuevo" class="max-w-xl" :dismissible="false" @close="cerrarToken">
        <div class="space-y-4">
            <flux:heading size="lg">Token generado</flux:heading>
            <flux:text>Cópielo ahora y guárdelo en la configuración de su sistema. <strong>No se volverá a mostrar.</strong></flux:text>
            <flux:input :value="$tokenNuevo" readonly copyable data-test="token-nuevo" />
            <div class="flex justify-end">
                <flux:button variant="primary" wire:click="cerrarToken">Ya lo guardé</flux:button>
            </div>
        </div>
    </flux:modal>
</flux:card>
