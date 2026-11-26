<?php

namespace App\Facturacion\Core\Dominio;

final readonly class ResultadoCalculo
{
    /**
     * @param  list<LineaCalculada>  $lineas
     */
    public function __construct(
        public array $lineas,
        public Totales $totales,
    ) {}
}
