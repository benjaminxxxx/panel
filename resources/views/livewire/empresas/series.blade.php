<flux:card class="space-y-6">
    <div>
        <flux:heading size="lg">Series</flux:heading>
        <flux:text>
            La numeración la lleva este servicio, así varios sistemas pueden compartir una serie sin duplicar números.
            Lo emitido en modo prueba se numera aparte y no gasta los números reales.
        </flux:text>
    </div>

    @if ($this->series->isNotEmpty())
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Serie</flux:table.column>
                <flux:table.column>Comprobante</flux:table.column>
                <flux:table.column align="end">Último número</flux:table.column>
                <flux:table.column align="end">En modo prueba</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->series as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-medium">{{ $item->serie }}</flux:table.cell>
                        <flux:table.cell>{{ $item->tipo_comprobante->nombre() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $item->correlativo }}</flux:table.cell>
                        <flux:table.cell align="end" class="text-zinc-500">{{ $item->correlativo_beta }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$item->activo ? 'green' : 'zinc'">{{ $item->activo ? 'Activa' : 'Inactiva' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" wire:click="alternar({{ $item->id }})">
                                {{ $item->activo ? 'Desactivar' : 'Activar' }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <form wire:submit="agregar" class="grid items-start gap-4 sm:grid-cols-4">
        <flux:select wire:model.live="tipo" label="Tipo">
            <flux:select.option value="01">Factura</flux:select.option>
            <flux:select.option value="03">Boleta</flux:select.option>
            <flux:select.option value="07">Nota de crédito</flux:select.option>
            <flux:select.option value="08">Nota de débito</flux:select.option>
        </flux:select>
        <flux:input wire:model="serie" label="Serie" maxlength="4"
                    :description="match ($tipo) { '07' => 'FC01 para facturas, BC01 para boletas.', '08' => 'FD01 para facturas, BD01 para boletas.', default => null }" />
        <flux:input wire:model="ultimo_correlativo" label="Último número emitido" type="number" min="0"
                    description="El real, en producción. 0 si es nueva." />
        <div class="sm:pt-6">
            <flux:button type="submit" variant="primary" class="w-full">Agregar serie</flux:button>
        </div>
    </form>
</flux:card>
