<x-layouts::app :title="__('Versiones')">
    <div class="flex w-full max-w-4xl flex-col gap-6">
        <div>
            <flux:heading size="xl">Versiones del sistema</flux:heading>
            <flux:text>Cada versión se firma al subirla. Los locales ven la publicada más nueva como "Actualización disponible".
                Última publicada: <strong>{{ $ultima?->version ?? 'ninguna' }}</strong></flux:text>
        </div>

        @if (session('ok'))
            <flux:callout variant="success" icon="check-circle" :heading="session('ok')" />
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="x-circle" :heading="$errors->first()" />
        @endif

        <flux:card>
            <form method="POST" action="{{ route('versiones.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf
                <flux:heading size="lg">Subir versión</flux:heading>
                <flux:input name="version" label="Versión" placeholder="1.0.1" :value="old('version')" required class="max-w-40" />
                <flux:textarea name="notas" label="Novedades (las ve la dueña antes de instalar)" rows="3">{{ old('notas') }}</flux:textarea>
                <flux:input name="archivo" type="file" label="Instalador (Restaurante-Setup-X.Y.Z.exe)" accept=".exe" required />
                <flux:checkbox name="publicar" value="1" checked label="Publicar ya" />
                <div><flux:button type="submit" variant="primary" icon="arrow-up-tray">Subir y firmar</flux:button></div>
            </form>
        </flux:card>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-900">
                    <tr><th class="px-4 py-3">Versión</th><th class="px-4 py-3">Novedades</th><th class="px-4 py-3">Tamaño</th><th class="px-4 py-3">Huella</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($versiones as $v)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $v->version }}
                                @if ($v->publicada) <flux:badge color="green" size="sm">Publicada</flux:badge> @else <flux:badge size="sm">Borrador</flux:badge> @endif
                            </td>
                            <td class="px-4 py-3">{{ $v->notas }}</td>
                            <td class="px-4 py-3">{{ number_format($v->tamano / 1048576, 1) }} MB</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ substr($v->sha256, 0, 12) }}…</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('versiones.publicar', $v) }}">
                                    @csrf
                                    <flux:button type="submit" size="sm" variant="ghost">{{ $v->publicada ? 'Retirar' : 'Publicar' }}</flux:button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-zinc-500">Aún no hay versiones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            <flux:heading size="lg">Versión instalada en cada local</flux:heading>
            <ul class="mt-2 text-sm">
                @foreach ($clientes as $c)
                    <li>{{ $c->nombre }}: <strong>{{ $c->version_reportada ?? '—' }}</strong>
                        <span class="text-zinc-500">({{ $c->ultimo_contacto_at?->diffForHumans() ?? 'sin contacto' }})</span></li>
                @endforeach
            </ul>
        </div>
    </div>
</x-layouts::app>
