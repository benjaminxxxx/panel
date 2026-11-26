@php
    use App\Facturacion\Core\Calculo\Decimal;
    $precio = fn ($v) => number_format((float) $v, max(2, min(4, Decimal::cantidadDecimales((string) $v))), '.', ',');
    $monto = fn ($v) => number_format((float) $v, 2, '.', ',');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $c->numero() }}</title>
    <style>
        @page { margin: 10pt 9pt 0 9pt; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 7.5pt; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .linea { border-top: 0.75pt dashed #000; margin: 5pt 0; }
        .logo { max-width: 150pt; max-height: 60pt; }
        .titulo { font-size: 8.5pt; font-weight: bold; margin-top: 4pt; }
        .items td { padding: 1.5pt 0; vertical-align: top; }
        .totales td { padding: 1pt 0; }
        .total td { font-size: 9pt; font-weight: bold; }
        .marca { border: 1pt solid #000; padding: 3pt; margin-bottom: 5pt; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    @if ($marcaAgua)
        <div class="marca">{{ $marcaAgua }}</div>
    @endif

    <div class="center">
        @if ($logo)
            <img src="{{ $logo }}" class="logo" alt=""><br>
        @endif
        <div class="bold" style="font-size: 9pt;">{{ $empresa->nombre_comercial ?: $empresa->razon_social }}</div>
        @if ($empresa->nombre_comercial)
            <div>{{ $empresa->razon_social }}</div>
        @endif
        <div>RUC {{ $empresa->ruc }}</div>
        <div>{{ $empresa->direccion }}</div>
        <div>{{ $empresa->distrito }} - {{ $empresa->provincia }}</div>
        @foreach ($c->informacionEn('cabecera') as $dato)
            <div>@if ($dato['etiqueta'] ?? null)<span class="bold">{{ $dato['etiqueta'] }}:</span> @endif{{ $dato['valor'] }}</div>
        @endforeach
        <div class="titulo">{{ $titulo }}</div>
        <div class="bold" style="font-size: 9pt;">{{ $c->serie }}-{{ str_pad((string) $c->correlativo, 8, '0', STR_PAD_LEFT) }}</div>
    </div>

    <div class="linea"></div>

    <div>Fecha: {{ $fecha->format('d/m/Y H:i') }}</div>
    <div>Cliente: {{ $c->cliente_razon_social }}</div>
    @if ($documentoCliente)
        <div>{{ $documentoCliente }}: {{ $c->cliente_numero_documento }}</div>
    @endif
    @if ($c->cliente_direccion)
        <div>Dir.: {{ $c->cliente_direccion }}</div>
    @endif
    @if ($c->esNota() && $c->afectado)
        <div>Modifica: {{ $c->afectado->tipo_comprobante->nombre() }} {{ $c->afectado->numero() }}</div>
        <div>Motivo: {{ $c->motivo_descripcion }}</div>
    @endif

    <div class="linea"></div>

    <table class="items">
        @foreach ($c->items as $item)
            <tr><td colspan="2">{{ $item->descripcion }}</td></tr>
            <tr>
                <td>{{ Decimal::compactar($item->cantidad) }} {{ $item->unidad }} x {{ $precio(Decimal::compactar($item->precio_unitario)) }}</td>
                <td class="right">{{ $monto($item->importe_total) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="linea"></div>

    <table class="totales">
        <tr><td>Op. gravada</td><td class="right">S/ {{ $monto($c->op_gravadas) }}</td></tr>
        @if ((float) $c->op_exoneradas > 0)<tr><td>Op. exonerada</td><td class="right">S/ {{ $monto($c->op_exoneradas) }}</td></tr>@endif
        @if ((float) $c->op_inafectas > 0)<tr><td>Op. inafecta</td><td class="right">S/ {{ $monto($c->op_inafectas) }}</td></tr>@endif
        @php($tasaIgv = rtrim(rtrim((string) ($c->items->firstWhere("tipo_afectacion_igv", "10")?->porcentaje_igv ?? "18"), "0"), "."))
                    <tr><td>IGV ({{ $tasaIgv }}%)</td><td class="right">S/ {{ $monto($c->igv) }}</td></tr>
        <tr class="total"><td>TOTAL</td><td class="right">S/ {{ $monto($c->total) }}</td></tr>
    </table>

    <div style="margin-top: 4pt;">{{ $c->monto_letras }}</div>
    @foreach ($c->informacionEn('centro') as $dato)
        <div>@if ($dato['etiqueta'] ?? null)<span class="bold">{{ $dato['etiqueta'] }}:</span> @endif{{ $dato['valor'] }}</div>
    @endforeach
    <div>Forma de pago: Contado</div>

    <div class="linea"></div>

    <div class="center">
        <img src="{{ $qr }}" style="width: 95pt; height: 95pt;" alt=""><br>
        @if ($c->hash)
            <div style="font-size: 6.5pt;">Hash: {{ $c->hash }}</div>
        @endif
        <div style="margin-top: 3pt;">Representación impresa de la {{ $titulo }}.</div>
        <div>Verifíquela en www.sunat.gob.pe</div>
    </div>
    @foreach ($c->informacionEn('pie') as $dato)
        <div>@if ($dato['etiqueta'] ?? null)<span class="bold">{{ $dato['etiqueta'] }}:</span> @endif{{ $dato['valor'] }}</div>
    @endforeach
    <div id="fin-ticket"></div>
</body>
</html>
