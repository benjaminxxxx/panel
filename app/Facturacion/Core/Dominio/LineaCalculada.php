<?php

namespace App\Facturacion\Core\Dominio;

/**
 * Ítem con todos los montos que exige el XML UBL 2.1.
 * Todos los montos son strings decimales exactos.
 */
final readonly class LineaCalculada
{
    public function __construct(
        public int $orden,
        public ?string $codigo,
        public string $descripcion,
        public string $unidad,
        public string $cantidad,
        /** Valor unitario sin IGV (hasta 10 decimales). */
        public string $valorUnitario,
        /** Precio unitario con IGV (hasta 10 decimales). */
        public string $precioUnitario,
        /** Catálogo 07. */
        public string $tipoAfectacionIgv,
        public string $porcentajeIgv,
        /** Valor de venta de la línea (sin IGV) = base imponible. */
        public string $valorVenta,
        public string $baseIgv,
        public string $igv,
        public string $totalImpuestos,
        /** Importe total de la línea (con IGV). */
        public string $importeTotal,
    ) {}
}
