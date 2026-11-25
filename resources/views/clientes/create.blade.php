<x-layouts::app :title="__('Nuevo cliente')">
    <div class="flex w-full max-w-xl flex-col gap-6">
        <flux:heading size="xl">Nuevo cliente</flux:heading>
        <form method="POST" action="{{ route('clientes.store') }}" class="flex flex-col gap-4">
            @csrf
            <flux:input name="nombre" label="Nombre del restaurante" :value="old('nombre')" required />
            <flux:input name="contacto" label="Contacto (dueña, teléfono)" :value="old('contacto')" />
            <flux:input name="vence_el" type="date" label="Pagado hasta" :value="old('vence_el', now()->addMonth()->toDateString())" />
            <flux:text>Al crearlo se genera su clave. Se muestra una sola vez: cópiala al instalar el sistema en el local.</flux:text>
            <div><flux:button type="submit" variant="primary">Crear cliente</flux:button></div>
        </form>
    </div>
</x-layouts::app>
