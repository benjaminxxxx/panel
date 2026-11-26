<?php

return [
    /*
    | Open Food Facts: base de datos abierta de productos (licencia ODbL; imágenes CC BY-SA).
    | Pide identificar el cliente con un User-Agent propio y respetar sus límites
    | (100 consultas de producto por minuto). Hay que mencionar la fuente donde se muestren los datos.
    */
    'off_producto' => 'https://world.openfoodfacts.org/api/v2/product/{codigo}.json',
    'off_busqueda' => 'https://search.openfoodfacts.org/search',
    'off_agente' => env('OFF_USER_AGENT', 'PanelSistemas/1.0 (catalogo de panaderias)'),
    'off_pais' => 'en:peru',

    /*
    | Solo productos que se venden en una panadería o pastelería. Categoría de Open Food Facts →
    | categoría del catálogo. El orden importa: gana la primera que coincide (leche condensada
    | es "Lácteos" aunque también sea "dulces para untar").
    */
    'categorias_panaderia' => [
        'en:eggs' => 'Huevos',
        'en:condensed-milks' => 'Lácteos',
        'en:milks' => 'Lácteos',
        'en:yogurts' => 'Lácteos',
        'en:cheeses' => 'Lácteos',
        'en:butters' => 'Lácteos',
        'en:dairies' => 'Lácteos',
        'en:margarines' => 'Repostería',
        'en:flours' => 'Repostería',
        'en:sugars' => 'Repostería',
        'en:baking-ingredients' => 'Repostería',
        'en:baking-mixes' => 'Repostería',
        'en:cake-decorations' => 'Repostería',
        'en:jams' => 'Repostería',
        'en:honeys' => 'Repostería',
        'en:sweet-spreads' => 'Repostería',
        'en:breads' => 'Panes y kekes',
        'en:cakes' => 'Panes y kekes',
        'en:pastries' => 'Panes y kekes',
        'en:viennoiseries' => 'Panes y kekes',
        'en:biscuits' => 'Galletas',
        'en:biscuits-and-cakes' => 'Galletas',
        'en:chocolates' => 'Chocolates y dulces',
        'en:candies' => 'Chocolates y dulces',
        'en:confectioneries' => 'Chocolates y dulces',
        'en:coffees' => 'Café e infusiones',
        'en:teas' => 'Café e infusiones',
        'en:herbal-teas' => 'Café e infusiones',
        'en:cocoa-and-its-products' => 'Café e infusiones',
        'en:breakfast-cereals' => 'Cereales',
        'en:fruit-juices' => 'Bebidas',
        'en:nectars' => 'Bebidas',
        'en:sodas' => 'Bebidas',
        'en:waters' => 'Bebidas',
        'en:plant-based-milks' => 'Bebidas',
    ],
];
