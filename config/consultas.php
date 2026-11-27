<?php

return [
    // Proveedor de datos de RENIEC para DNI (opcional, de pago: Decolecta, apis.net.pe, etc.).
    // La URL lleva {dni}; el token va como "Authorization: Bearer". Sin esto, el DNI se busca en
    // SUNAT (solo encuentra a quienes tienen RUC).
    'dni_url' => env('CONSULTA_DNI_URL', ''),
    'dni_token' => env('CONSULTA_DNI_TOKEN', ''),

    // Relevo para RUC (provisional, mientras no esté cargado el padrón): el servicio peru-consult-api
    // propio en otro hosting. URL con {ruc} y su token, ej.
    // https://apisunat.perudevsolutions.com/public/api/v1/ruc/{ruc}?token=XXXX
    'ruc_url' => env('CONSULTA_RUC_URL', ''),

    // Token para sistemas propios que no son locales ni empresas (ej. otra web del proveedor).
    'token' => env('CONSULTA_TOKEN', ''),
];
