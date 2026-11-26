<x-layouts::app :title="__('Clientes')">
    <div class="flex w-full flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">Clientes</flux:heading>
                <flux:text>Restaurantes y panaderías que alquilan el sistema. El estado se envía firmado a cada local.</flux:text>
            </div>
            <flux:button variant="primary" icon="plus" :href="route('clientes.create')" wire:navigate>Nuevo cliente</flux:button>
        </div>

        @if (session('ok'))
            <flux:callout variant="success" icon="check-circle" :heading="session('ok')" />
        @endif

        @if ($alertas->isNotEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle" heading="Requieren atención">
                <flux:callout.text>
                    <ul class="list-disc ps-5">
                        @foreach ($alertas as $f)
                            <li>
                                <a class="underline" href="{{ route('clientes.show', $f['cliente']) }}">{{ $f['cliente']->nombre }}</a>:
                                {{ $f['estado']['estado'] === 'bloqueado' ? 'BLOQUEADO — '.$f['estado']['motivo'] : $f['estado']['aviso'] }}
                            </li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Vence</th>
                        <th class="px-4 py-3">Último contacto</th>
                        <th class="px-4 py-3">Versión</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($clientes as $f)
                        @php($c = $f['cliente'])
                        <tr>
                            <td class="px-4 py-3">
                                <a class="font-medium underline-offset-4 hover:underline" href="{{ route('clientes.show', $c) }}" wire:navigate>{{ $c->nombre }}</a>
                                <flux:badge size="sm" :color="$c->aplicacion === 'panaderia' ? 'pink' : 'zinc'">{{ config("licencias.aplicaciones.{$c->aplicacion}") }}</flux:badge>
                                <div class="text-zinc-500">{{ $c->contacto }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($f['estado']['estado'] === 'bloqueado')
                                    <flux:badge color="red">Bloqueado</flux:badge>
                                @elseif ($f['estado']['aviso'])
                                    <flux:badge color="amber">Por vencer</flux:badge>
                                @else
                                    <flux:badge color="green">Activo</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $c->vence_el?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $c->ultimo_contacto_at?->diffForHumans() ?? 'Nunca' }}</td>
                            <td class="px-4 py-3">{{ $c->version_reportada ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-zinc-500">Aún no hay clientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
