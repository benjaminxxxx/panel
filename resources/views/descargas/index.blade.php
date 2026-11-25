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
            <flux:text>Se usan con la computadora del local, por el WiFi del restaurante.</flux:text>
            <ol class="list-decimal space-y-1 ps-5 text-sm">
                <li>Conecta la tablet al mismo WiFi que la computadora del local.</li>
                <li>En la computadora, abre el sistema → <strong>Panel → Celulares</strong> y escanea el QR con la cámara de la tablet.</li>
                <li><strong>Android</strong>: si la app ya está instalada se abre conectada al local; si no, descarga la app y ábrela.
                    Después se actualiza sola.</li>
                <li><strong>iPhone</strong>: abre el sistema en Safari → Compartir → <strong>Agregar a inicio</strong>.</li>
            </ol>
            @php($apk = \App\Models\Version::ultimaPublicada('android'))
            @if ($apk)
                <div>
                    <flux:button icon="arrow-down-tray" :href="route('app.android')">Descargar app Android · versión {{ $apk->version }}</flux:button>
                </div>
            @endif
        </flux:card>
    </div>
</x-layouts::app>
