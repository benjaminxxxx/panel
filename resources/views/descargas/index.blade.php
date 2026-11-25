<x-layouts::app :title="__('Instalar')">
    <div class="flex w-full max-w-3xl flex-col gap-6">
        <div>
            <flux:heading size="xl">Instalar el sistema en un restaurante</flux:heading>
            <flux:text>Abre esta página en el equipo del local. Siempre descarga la versión más reciente.</flux:text>
        </div>

        <flux:card class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <flux:icon name="computer-desktop" class="size-8" />
                <flux:heading size="lg">Computadora del local (Windows)</flux:heading>
            </div>
            <flux:text>La computadora guarda los datos y sirve el sistema a las tablets y celulares por el WiFi.</flux:text>
            @if ($ultima)
                <div>
                    <flux:button variant="primary" icon="arrow-down-tray" :href="route('descargas.windows')">
                        Descargar instalador · versión {{ $ultima->version }} ({{ number_format($ultima->tamano / 1048576, 1) }} MB)
                    </flux:button>
                </div>
                <ol class="list-decimal space-y-1 ps-5 text-sm">
                    <li>Abre <strong>Restaurante-Setup-{{ $ultima->version }}.exe</strong> y acepta el permiso de administrador.</li>
                    <li>Al terminar se abre el sistema y pide el <strong>código de activación</strong>.</li>
                    <li>En <a class="underline" href="{{ route('dashboard') }}">Clientes</a> → el restaurante → <strong>Generar código de activación</strong>. Vale 2 horas y una sola vez.</li>
                </ol>
            @else
                <flux:callout variant="warning" icon="exclamation-triangle" heading="Aún no hay versiones publicadas. Sube una en Versiones." />
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <flux:icon name="device-phone-mobile" class="size-8" />
                <flux:heading size="lg">Tablets y celulares (Android o iPhone)</flux:heading>
            </div>
            <flux:text>No se instala nada desde aquí: se usan con la computadora del local, por el WiFi del restaurante.</flux:text>
            <ol class="list-decimal space-y-1 ps-5 text-sm">
                <li>Conecta la tablet al mismo WiFi que la computadora del local.</li>
                <li>En la computadora, abre el sistema → <strong>Panel → Instalar en celulares</strong>: muestra la dirección (ej. http://192.168.1.20:8080).</li>
                <li>En la tablet, abre esa dirección en Chrome (Android) o Safari (iPhone).</li>
                <li>Android: menú ⋮ → <strong>Agregar a la pantalla principal</strong>. iPhone: Compartir → <strong>Agregar a inicio</strong>.</li>
            </ol>
        </flux:card>
    </div>
</x-layouts::app>
