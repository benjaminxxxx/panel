<x-layouts::app :title="__('Nuevo cliente')">
    <div class="flex w-full max-w-xl flex-col gap-6">
        <flux:heading size="xl">Nuevo cliente</flux:heading>
        <form method="POST" action="{{ route('clientes.store') }}" class="flex flex-col gap-4">
            @csrf
            <flux:select name="aplicacion" label="Sistema que alquila">
                @foreach (config('licencias.aplicaciones') as $clave => $nombre)
                    <flux:select.option :value="$clave" :selected="old('aplicacion', request('aplicacion', 'restaurante')) === $clave">{{ $nombre }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input name="nombre" label="Nombre del local" :value="old('nombre')" required />
            <flux:input name="contacto" label="Contacto (dueña, teléfono)" :value="old('contacto')" />
            <flux:input name="vence_el" type="date" label="Pagado hasta" :value="old('vence_el', now()->addMonth()->toDateString())" />
            <flux:text>El sistema no se puede cambiar después: su código de activación solo sirve en ese programa.</flux:text>
            <div><flux:button type="submit" variant="primary">Crear cliente</flux:button></div>
        </form>
    </div>
</x-layouts::app>
