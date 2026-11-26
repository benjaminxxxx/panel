<x-layouts::app :title="__('Instalar')">
    <div class="flex w-full max-w-3xl flex-col gap-6">
        <div>
            <flux:heading size="xl">Instalar el sistema en un local</flux:heading>
            <flux:text>Abre esta página en la computadora del local. Siempre descarga la versión más reciente de cada sistema.</flux:text>
        </div>

        @foreach (config('licencias.aplicaciones') as $app => $nombre)
            @php($v = $ultimas[$app] ?? null)
            <flux:card class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <flux:icon :name="$app === 'panaderia' ? 'cake' : 'building-storefront'" class="size-8" />
                    <flux:heading size="lg">{{ $nombre }} · computadora del local (Windows)</flux:heading>
                </div>
                @if ($app === 'panaderia')
                    <flux:text>Caja táctil con lector de código de barras, ticketera y facturación electrónica. Vende sin internet;
                        las boletas y facturas se envían cuando vuelve la conexión. Otras cajas, tablets y celulares del WiFi la usan desde el navegador.</flux:text>
                @else
                    <flux:text>La computadora guarda los datos y sirve el sistema a las tablets y celulares por el WiFi.</flux:text>
                @endif
                @if ($v)
                    <div>
                        <flux:button variant="primary" icon="arrow-down-tray" :href="route('descargas.windows', $app)">
                            Descargar instalador · versión {{ $v->version }} ({{ number_format($v->tamano / 1048576, 1) }} MB)
                        </flux:button>
                    </div>
                    <ol class="list-decimal space-y-1 ps-5 text-sm">
                        <li>Abre <strong>{{ $v->nombreArchivo() }}</strong> y acepta el permiso de administrador.</li>
                        <li>Al terminar se abre el sistema y pide el <strong>código de activación</strong>.</li>
                        <li>En <a class="underline" href="{{ route('clientes.index') }}">Clientes</a> → el local → <strong>Generar código de activación</strong>. Vale 2 horas y una sola vez.</li>
                    </ol>
                @else
                    <flux:callout variant="warning" icon="exclamation-triangle" heading="Aún no hay versiones publicadas de {{ $nombre }}. Sube una en Versiones." />
                @endif
            </flux:card>
        @endforeach

        <flux:card class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <flux:icon name="device-phone-mobile" class="size-8" />
                <flux:heading size="lg">Tablets y celulares (Android o iPhone)</flux:heading>
            </div>
            <flux:text>Se usan con la computadora del local, por el WiFi.</flux:text>
            <ol class="list-decimal space-y-1 ps-5 text-sm">
                <li>Conecta la tablet al mismo WiFi que la computadora del local.</li>
                <li><strong>Restaurante</strong>: en la computadora abre <strong>Panel → Celulares</strong> y escanea el QR.
                    <strong>Panadería</strong>: <strong>Ajustes → Equipos</strong> y escanea el QR.</li>
                <li><strong>Android</strong> (restaurante): si la app ya está instalada se abre conectada al local; si no, descárgala. Se actualiza sola.</li>
                <li><strong>iPhone</strong> o panadería: abre el sistema en el navegador → <strong>Agregar a inicio</strong>.</li>
            </ol>
            @php($apk = \App\Models\Version::ultimaPublicada('android'))
            @if ($apk)
                <div>
                    <flux:button icon="arrow-down-tray" :href="route('app.android')">Descargar app Android del restaurante · versión {{ $apk->version }}</flux:button>
                </div>
            @endif
        </flux:card>
    </div>
</x-layouts::app>
