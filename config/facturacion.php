<?php

return [

    /*
    | Sistemas que pueden consumir la API. Cada token de API pertenece a una empresa
    | y a uno de estos sistemas; así un comprobante siempre sabe de dónde vino.
    */
    'sistemas' => [
        'pos' => 'Punto de venta',
        'alquiler' => 'Alquileres',
        'restaurant' => 'Restaurante',
        'hotel' => 'Hotel',
    ],

    /*
    | Disco donde se guardan XML firmados y CDR. Debe ser PRIVADO: nunca 'public'.
    */
    'disco' => env('FACTURACION_DISCO', 'local'),

    'directorio' => 'facturacion',

    /*
    | Caché de plantillas Twig de Greenter (acelera la generación del XML).
    */
    'cache_xml' => storage_path('framework/cache/greenter'),

    /*
    | Tasas de IGV (tasa total con IPM) que se aceptan por ítem gravado. 2026: 18 general
    | (15.5 IGV + 2.5 IPM, Ley 32387) y 10.5 para restaurantes y hoteles MYPE (8 + 2.5, Ley 31556).
    | Actualizar aquí cuando cambie la ley.
    */
    'tasas_igv' => ['18', '10.5'],

    /*
    | Bienes y servicios sujetos a detracción (catálogo 54) que se pueden informar en la factura,
    | con su porcentaje habitual. Ver panaderia/docs/detraccion.md.
    */
    'detracciones' => [
        '001' => ['nombre' => 'Azúcar y melaza de caña', 'porcentaje' => '10'],
        '023' => ['nombre' => 'Leche', 'porcentaje' => '4'],
        '025' => ['nombre' => 'Fabricación de bienes por encargo', 'porcentaje' => '10'],
        '035' => ['nombre' => 'Bienes exonerados del IGV', 'porcentaje' => '1.5'],
        '037' => ['nombre' => 'Demás servicios gravados con el IGV', 'porcentaje' => '12'],
    ],

];
