<x-layouts::app :title="$p->exists ? $p->nombre : __('Nuevo producto')">
    <div class="flex w-full max-w-xl flex-col gap-6">
        <flux:heading size="xl">{{ $p->exists ? 'Editar producto' : 'Nuevo producto del catálogo' }}</flux:heading>
        @if ($errors->any())
            <flux:callout variant="danger" icon="x-circle" :heading="$errors->first()" />
        @endif
        <form method="POST" action="{{ $p->exists ? route('catalogo.update', $p) : route('catalogo.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf
            @if ($p->exists) @method('PUT') @endif
            <flux:input name="codigo_barras" label="Código de barras" :value="old('codigo_barras', $p->codigo_barras)" required autofocus />
            <flux:input name="nombre" label="Nombre" placeholder="Leche evaporada entera" :value="old('nombre', $p->nombre)" required />
            <div class="grid grid-cols-2 gap-4">
                <flux:input name="marca" label="Marca" :value="old('marca', $p->marca)" />
                <flux:input name="presentacion" label="Presentación" placeholder="400 g" :value="old('presentacion', $p->presentacion)" />
            </div>
            <div class="grid grid-cols-3 gap-4">
                <flux:input name="categoria" label="Categoría" placeholder="Lácteos" :value="old('categoria', $p->categoria)" />
                <flux:select name="unidad" label="Unidad">
                    @foreach (\App\Models\CatalogoProducto::UNIDADES as $codigo => $nombre)
                        <flux:select.option :value="$codigo" :selected="old('unidad', $p->unidad) === $codigo">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input name="precio_sugerido" label="Precio sugerido (S/)" inputmode="decimal" :value="old('precio_sugerido', $p->precio_sugerido)" />
            </div>
            <div class="flex items-end gap-4">
                @if ($p->foto)
                    <img src="{{ route('api.catalogo.foto', $p) }}" alt="" class="size-16 rounded-lg object-cover">
                @endif
                <flux:input name="foto" type="file" accept="image/*" label="Foto (opcional, máx. 2 MB)" />
            </div>
            @if ($p->exists)
                <flux:checkbox name="activo" value="1" :checked="$p->activo" label="Visible para los locales" />
            @endif
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Guardar</flux:button>
                <flux:button :href="route('catalogo.index')" wire:navigate>Cancelar</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
