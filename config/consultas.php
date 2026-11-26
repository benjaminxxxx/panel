<?php

return [
    // Proveedor de datos de RENIEC para DNI (opcional, de pago: Decolecta, apis.net.pe, etc.).
    // La URL lleva {dni}; el token va como "Authorization: Bearer". Sin esto, el DNI se busca en
    // SUNAT (solo encuentra a quienes tienen RUC).
    'dni_url' => env('CONSULTA_DNI_URL', ''),
    'dni_token' => env('CONSULTA_DNI_TOKEN', ''),

    // Token para sistemas propios que no son locales ni empresas (ej. otra web del proveedor).
    'token' => env('CONSULTA_TOKEN', ''),
];
