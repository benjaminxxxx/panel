<flux:card class="space-y-4">
    <flux:heading size="lg">Comprobantes emitidos</flux:heading>

    @if ($this->comprobantes->isEmpty())
        <flux:text>Todavía no hay comprobantes. Se emiten desde sus sistemas conectados.</flux:text>
    @else
        <flux:table :paginate="$this->comprobantes">
            <flux:table.columns>
                <flux:table.column>Número</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Cliente</flux:table.column>
                <flux:table.column>Sistema</flux:table.column>
                <flux:table.column align="end">Total</flux:table.column>
                <flux:table.column>Estado SUNAT</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->comprobantes as $c)
                    @php
                        $color = match ($c->estado) {
                            \App\Facturacion\Core\Enums\EstadoComprobante::Aceptado => 'green',
                            \App\Facturacion\Core\Enums\EstadoComprobante::Observado => 'lime',
                            \App\Facturacion\Core\Enums\EstadoComprobante::Rechazado => 'red',
                            default => 'amber',
                        };
                    @endphp
                    <flux:table.row :key="$c->id">
                        <flux:table.cell class="font-medium">
                            {{ $c->numero() }}
                            @if ($c->esNota())
                                <div class="text-xs font-normal text-zinc-500">{{ $c->tipo_comprobante->nombre() }} de {{ $c->afectado?->numero() }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $c->fecha_emision->setTimezone('America/Lima')->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell class="max-w-56 truncate">{{ $c->cliente_razon_social }}</flux:table.cell>
                        <flux:table.cell>{{ $c->sistema }}</flux:table.cell>
                        <flux:table.cell align="end">S/ {{ $c->total }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:tooltip :content="$c->sunat_descripcion ?? ''">
                                <flux:badge size="sm" :color="$color">{{ str_replace('_', ' ', $c->estado->value) }}</flux:badge>
                            </flux:tooltip>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="space-x-1">
                            <flux:button size="xs" variant="ghost" target="_blank" :href="route('empresas.comprobantes.archivo', [$empresa, $c, 'a4'])">PDF</flux:button>
                            <flux:button size="xs" variant="ghost" target="_blank" :href="route('empresas.comprobantes.archivo', [$empresa, $c, 'ticket'])">Ticket</flux:button>
                            @if ($c->xml_path)
                                <flux:button size="xs" variant="ghost" :href="route('empresas.comprobantes.archivo', [$empresa, $c, 'xml'])">XML</flux:button>
                            @endif
                            @if ($c->cdr_path)
                                <flux:button size="xs" variant="ghost" :href="route('empresas.comprobantes.archivo', [$empresa, $c, 'cdr'])">CDR</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</flux:card>
