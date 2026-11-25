<?php

return [
    // Llave privada Ed25519 (base64, 64 bytes) con la que se firman licencias y versiones.
    // Se genera con: php artisan licencias:llaves. La pública va embebida en el programa del local.
    'llave_privada' => env('LICENCIA_LLAVE_PRIVADA'),

    // Días después del vencimiento antes del bloqueo automático.
    'dias_gracia' => (int) env('LICENCIA_DIAS_GRACIA', 3),

    // Días antes del vencimiento en que el local ve el aviso "tu pago vence el...".
    'dias_aviso' => (int) env('LICENCIA_DIAS_AVISO', 3),

    // Cuántos días puede funcionar el local sin conectarse al panel.
    'dias_sin_conexion' => (int) env('LICENCIA_DIAS_SIN_CONEXION', 7),

    // Token con el que GitHub Actions publica versiones (POST /api/v1/versiones). Solo sube; no firma.
    'token_publicacion' => env('LICENCIA_TOKEN_PUBLICACION'),

    'motivo_por_defecto' => 'Servicio suspendido por falta de pago. Comunícate con tu proveedor para reactivarlo.',
];
