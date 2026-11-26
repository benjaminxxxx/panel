<x-layouts::app :title="__('Catálogo de productos')">
    <div class="flex w-full flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">Catálogo de productos</flux:heading>
                <flux:text>Productos de marca con código de barras ({{ $total }}). Las panaderías los buscan y descargan a su caja con su propio precio.
                    Los productos de panadería que escanean y no están aquí se traen solos de <a class="underline" href="https://pe.openfoodfacts.org" target="_blank" rel="noopener">Open Food Facts</a> (licencia ODbL). Carga masiva: <code>php artisan catalogo:openfoodfacts</code>.</flux:text>
            </div>
            <flux:button variant="primary" icon="plus" :href="route('catalogo.create')" wire:navigate>Nuevo producto</flux:button>
        </div>

        @if (session('ok'))
            <flux:callout variant="success" icon="check-circle" :heading="session('ok')" />
        @endif
        @if ($errors->any())
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="$errors->first()" />
        @endif

        <div class="flex flex-wrap items-end gap-4">
            <form method="GET" action="{{ route('catalogo.index') }}" class="flex flex-1 gap-2">
                <flux:input name="q" :value="$q" placeholder="Buscar por nombre, marca o código" class="flex-1" />
                <flux:button type="submit" icon="magnifying-glass">Buscar</flux:button>
            </form>
            <form method="POST" action="{{ route('catalogo.importar') }}" enctype="multipart/form-data" class="flex items-end gap-2">
                @csrf
                <flux:input name="archivo" type="file" accept=".csv,.txt" label="Importar CSV" required />
                <flux:button type="submit" icon="arrow-up-tray">Importar</flux:button>
            </form>
        </div>
        <flux:text class="text-sm">CSV con encabezados: <code>codigo_barras, nombre, marca, presentacion, categoria, unidad, precio_sugerido</code>
            (coma o punto y coma). Si el código ya existe, se actualiza. Sirve para cargar los productos del sistema anterior de un cliente.</flux:text>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3">Precio sugerido</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($productos as $p)
                        <tr @class(['opacity-50' => ! $p->activo])>
                            <td class="px-4 py-3 font-mono text-xs">{{ $p->codigo_barras }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($p->foto)
                                        <img src="{{ route('api.catalogo.foto', $p) }}" alt="" class="size-10 rounded-lg object-cover">
                                    @endif
                                    <div>
                                        <div class="font-medium">{{ $p->nombre }} <span class="text-zinc-500">{{ $p->presentacion }}</span></div>
                                        <div class="text-zinc-500">{{ $p->marca }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $p->categoria ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $p->precio_sugerido ? 'S/ '.$p->precio_sugerido : '—' }}</td>
                            <td class="px-4 py-3 text-end">
                                @unless ($p->activo) <flux:badge size="sm">Oculto</flux:badge> @endunless
                                @if ($p->fuente === 'openfoodfacts') <flux:badge size="sm" color="lime">Open Food Facts</flux:badge> @endif
                                <flux:button size="sm" variant="ghost" :href="route('catalogo.edit', $p)" wire:navigate>Editar</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-zinc-500">No hay productos{{ $q ? ' con esa búsqueda' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $productos->links() }}
    </div>
</x-layouts::app>
