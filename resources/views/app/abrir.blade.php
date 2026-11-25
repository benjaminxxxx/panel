<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#5423e7">
    <title>Restaurante en tu celular</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f7f7f8; color: #121217; }
        main { max-width: 480px; margin: 0 auto; padding: 32px 20px; display: flex; flex-direction: column; gap: 16px; }
        .marca { width: 72px; height: 72px; border-radius: 18px; background: #5423e7; color: #fff; font-size: 44px; font-weight: 800;
                 display: flex; align-items: center; justify-content: center; position: relative; }
        .marca::after { content: ''; position: absolute; top: 12px; right: 12px; width: 12px; height: 12px; border-radius: 50%; background: #ffb800; }
        h1 { margin: 0; font-size: 26px; letter-spacing: -0.02em; }
        p { margin: 0; line-height: 1.5; color: #44445a; }
        .boton { display: flex; align-items: center; justify-content: center; min-height: 60px; padding: 14px 20px; border-radius: 14px;
                 font-size: 18px; font-weight: 700; text-decoration: none; text-align: center; }
        .principal { background: #121217; color: #fff; }
        .secundario { background: #fff; color: #121217; border: 2px solid #d1d1db; }
        .caja { background: #fff; border-radius: 20px; padding: 18px; box-shadow: 0 4px 24px rgb(18 18 23 / .06); display: flex; flex-direction: column; gap: 12px; }
        ol { margin: 0; padding-left: 20px; line-height: 1.7; }
        .sutil { color: #6c6c89; font-size: 14px; }
        .aviso { background: #fff1c2; color: #6b4500; padding: 12px 14px; border-radius: 12px; font-weight: 600; }
        code { font-size: 15px; word-break: break-all; }
    </style>
</head>
<body>
<main>
    <div class="marca">R</div>

    @if (! $servidor)
        <h1>Restaurante</h1>
        <p class="aviso">Este enlace no trae la dirección del local. Escanea el código QR desde la computadora del local: Panel → Celulares.</p>
    @elseif ($esAndroid)
        @if ($instalar)
            <h1>Instala la app Restaurante</h1>
            <p>Todavía no la tienes en este celular. Son unos segundos.</p>
        @else
            <h1>Abriendo Restaurante…</h1>
            <p>Si no se abre sola, toca el botón.</p>
        @endif
        <a class="boton {{ $instalar ? 'secundario' : 'principal' }}" href="{{ $enlaceApp }}">Abrir la app</a>

        @if ($apk)
            <div class="caja">
                <a class="boton {{ $instalar ? 'principal' : 'secundario' }}" href="{{ url('/app/android/descargar') }}">Descargar la app (versión {{ $apk->version }})</a>
                <ol>
                    <li>Abre el archivo <strong>Restaurante.apk</strong> descargado.</li>
                    <li>Si Android lo pide, permite <strong>instalar apps de esta fuente</strong>.</li>
                    <li>Toca <strong>Instalar</strong> y luego vuelve aquí y toca <strong>Abrir la app</strong>.</li>
                </ol>
                <p class="sutil">Después se actualiza sola: al abrirla avisa si hay una versión nueva.</p>
            </div>
        @else
            <p class="aviso">Aún no hay app Android publicada. Mientras tanto, usa el sistema en el navegador:</p>
            <a class="boton secundario" href="{{ $servidor }}">Abrir en el navegador</a>
        @endif
    @else
        <h1>Restaurante en tu celular</h1>
        <a class="boton principal" href="{{ $servidor }}">Abrir el sistema</a>
        <div class="caja">
            <p><strong>Para tenerlo como app:</strong></p>
            <ol>
                <li>Abre el sistema con el botón de arriba (en Safari).</li>
                <li>Toca <strong>Compartir</strong> → <strong>Agregar a inicio</strong>.</li>
            </ol>
            <p class="sutil">El celular debe estar en el WiFi del local: <code>{{ $servidor }}</code></p>
        </div>
    @endif
</main>
@if ($servidor && $esAndroid && ! $instalar)
    <script>
        // Intenta abrir la app; si no está instalada, Chrome va a la página para instalarla.
        location.href = @json($enlaceApp);
    </script>
@endif
</body>
</html>
