<x-layouts::app :title="$c->nombre">
    <div class="flex w-full max-w-4xl flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ $c->nombre }} <flux:badge size="sm">{{ config("licencias.aplicaciones.{$c->aplicacion}") }}</flux:badge></flux:heading>
                <flux:text>Código {{ $c->codigo }} · clave {{ $c->clave_inicio }}… · versión {{ $c->version_reportada ?? '—' }} ·
                    último contacto {{ $c->ultimo_contacto_at?->diffForHumans() ?? 'nunca' }}</flux:text>
            </div>
            @if ($estado['estado'] === 'bloqueado')
                <flux:badge color="red" size="lg">Bloqueado</flux:badge>
            @else
                <flux:badge color="green" size="lg">Activo</flux:badge>
            @endif
        </div>

        @if (session('ok'))
            <flux:callout variant="success" icon="check-circle" :heading="session('ok')" />
        @endif
        @if (session('clave'))
            <flux:callout variant="warning" icon="key" heading="Clave del cliente (se muestra una sola vez)">
                <flux:callout.text><code class="select-all text-base">{{ session('clave') }}</code></flux:callout.text>
            </flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="x-circle" :heading="$errors->first()" />
        @endif

        <flux:card class="flex flex-col gap-3">
            <flux:heading size="lg">Instalar en el local</flux:heading>
            @if (session('activacion'))
                <flux:text>Código de activación (vale {{ \App\Models\Cliente::MINUTOS_ACTIVACION / 60 }} horas, una sola vez):</flux:text>
                <div class="select-all font-mono text-4xl font-bold tracking-widest">{{ session('activacion') }}</div>
                <flux:text>Escríbelo en la pantalla "Activar este local" que aparece al terminar la instalación.</flux:text>
            @else
                <flux:text>1) Descarga el instalador en <a class="underline" href="{{ route('descargas') }}">Instalar</a>.
                    2) Al terminar, el sistema pide un código: genéralo aquí. Activar de nuevo reemplaza la instalación anterior.</flux:text>
            @endif
            <form method="POST" action="{{ route('clientes.activacion', $c) }}">
                @csrf
                <flux:button type="submit" icon="key">Generar código de activación</flux:button>
            </form>
        </flux:card>

        @if ($c->aplicacion === 'panaderia')
            <flux:card class="flex flex-col gap-3">
                <flux:heading size="lg">Empezar de cero</flux:heading>
                <flux:text>En el local, <strong>Ajustes → Sistema → Empezar de cero</strong> borra ventas, productos y clientes de prueba.
                    Se puede usar una sola vez; para permitirlo de nuevo, genera un permiso aquí (el local lo recibe en ≈1 minuto).</flux:text>
                @if ($c->reinicio_permiso)
                    <flux:badge color="amber" class="self-start">Permiso pendiente de usar</flux:badge>
                @endif
                <form method="POST" action="{{ route('clientes.reinicio', $c) }}" onsubmit="return confirm('¿Permitir que el local borre todas sus ventas y productos una vez más?')">
                    @csrf
                    <flux:button type="submit" icon="arrow-path">Permitir empezar de cero otra vez</flux:button>
                </form>
            </flux:card>
        @endif

        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">Acceso</flux:heading>
            @if ($estado['estado'] === 'bloqueado')
                <flux:text>Motivo que ve el local: <strong>{{ $estado['motivo'] }}</strong></flux:text>
                @if ($c->estado === 'bloqueado')
                    <form method="POST" action="{{ route('clientes.habilitar', $c) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" icon="lock-open">Habilitar</flux:button>
                    </form>
                @else
                    <flux:text>Bloqueado automáticamente por vencimiento: registra un pago o desactiva el bloqueo automático.</flux:text>
                @endif
            @else
                @if ($estado['aviso'])
                    <flux:text>Aviso que ve el local: {{ $estado['aviso'] }}</flux:text>
                @endif
                <form method="POST" action="{{ route('clientes.bloquear', $c) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-72 flex-1">
                        <flux:input name="motivo" label="Motivo (opcional)" placeholder="{{ config('licencias.motivo_por_defecto') }}" />
                    </div>
                    <flux:button type="submit" variant="danger" icon="lock-closed">Bloquear ahora</flux:button>
                </form>
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">Pagos</flux:heading>
            <flux:text>Pagado hasta: <strong>{{ $c->vence_el?->format('d/m/Y') ?? 'sin fecha' }}</strong></flux:text>
            <form method="POST" action="{{ route('clientes.pagos.store', $c) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <flux:input name="monto" type="number" step="0.01" min="0" label="Monto (S/)" required class="max-w-36" />
                <flux:input name="meses" type="number" min="1" max="24" value="1" label="Meses" required class="max-w-24" />
                <div class="min-w-48 flex-1"><flux:input name="nota" label="Nota" placeholder="Yape, efectivo…" /></div>
                <flux:button type="submit" variant="primary" icon="banknotes">Registrar pago</flux:button>
            </form>
            @if ($c->pagos->isNotEmpty())
                <table class="w-full text-left text-sm">
                    <thead class="text-zinc-500"><tr><th class="py-2">Fecha</th><th>Monto</th><th>Meses</th><th>Nota</th></tr></thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($c->pagos as $p)
                            <tr><td class="py-2">{{ $p->pagado_el->format('d/m/Y') }}</td><td>S/ {{ $p->monto }}</td><td>{{ $p->meses }}</td><td>{{ $p->nota }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </flux:card>

        <flux:card>
            <form method="POST" action="{{ route('clientes.update', $c) }}" class="flex flex-col gap-4">
                @csrf @method('PUT')
                <flux:heading size="lg">Datos</flux:heading>
                <flux:input name="nombre" label="Nombre" :value="$c->nombre" required />
                <flux:input name="contacto" label="Contacto" :value="$c->contacto" />
                <flux:input name="vence_el" type="date" label="Pagado hasta" :value="$c->vence_el?->toDateString()" />
                <flux:checkbox name="bloqueo_automatico" value="1" :checked="$c->bloqueo_automatico"
                    label="Bloquear automáticamente {{ config('licencias.dias_gracia') }} días después del vencimiento" />
                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit" variant="primary">Guardar</flux:button>
                </div>
            </form>
            <form method="POST" action="{{ route('clientes.clave', $c) }}" class="mt-4"
                onsubmit="return confirm('El local dejará de conectarse hasta que pongas la clave nueva en su configuración. ¿Continuar?')">
                @csrf
                <flux:button type="submit" variant="ghost" icon="key">Generar clave nueva</flux:button>
            </form>
        </flux:card>
    </div>
</x-layouts::app>
