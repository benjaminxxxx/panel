@php
    use App\Facturacion\Core\Calculo\Decimal;
    // Muestra 2 decimales, o hasta 4 si el precio los tiene (ej. 0.125).
    $precio = fn ($v) => number_format((float) $v, max(2, min(4, Decimal::cantidadDecimales((string) $v))), '.', ',');
    $monto = fn ($v) => number_format((float) $v, 2, '.', ',');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $c->numero() }}</title>
    <style>
        @page { margin: 28pt 30pt; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8.5pt; color: #1f2937; }
        table { border-collapse: collapse; width: 100%; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .cabecera td { vertical-align: top; }
        .logo { max-width: 150pt; max-height: 75pt; }
        .empresa { font-size: 12pt; font-weight: bold; }
        .caja-numero { border: 1.5pt solid #111827; border-radius: 6pt; text-align: center; padding: 10pt 6pt; }
        .caja-numero .ruc { font-size: 11pt; font-weight: bold; }
        .caja-numero .tipo { background: #111827; color: #fff; font-weight: bold; font-size: 10pt; padding: 5pt 2pt; margin: 6pt 0; }
        .caja-numero .numero { font-size: 12pt; font-weight: bold; }
        .cliente { margin-top: 14pt; border: 0.75pt solid #d1d5db; border-radius: 4pt; }
        .cliente td { padding: 3pt 6pt; }
        .items { margin-top: 12pt; }
        .items th { background: #f3f4f6; border-bottom: 1pt solid #9ca3af; padding: 5pt 4pt; font-size: 7.5pt; text-transform: uppercase; }
        .items td { padding: 4pt; border-bottom: 0.5pt solid #e5e7eb; vertical-align: top; }
        .totales td { padding: 3pt 6pt; }
        .totales .total td { border-top: 1pt solid #111827; font-size: 10pt; font-weight: bold; }
        .letras { margin-top: 10pt; padding: 6pt; background: #f9fafb; border-left: 2pt solid #111827; }
        .pie { margin-top: 16pt; border-top: 0.75pt solid #d1d5db; padding-top: 8pt; font-size: 7.5pt; }
        .marca-agua { position: fixed; top: 300pt; left: -60pt; right: -60pt; text-align: center; font-size: 40pt; font-weight: bold; white-space: nowrap;
                      color: #dc2626; opacity: 0.12; transform: rotate(-30deg); }
    </style>
</head>
<body>
    @if ($marcaAgua)
        <div class="marca-agua">{{ $marcaAgua }}</div>
    @endif

    <table class="cabecera">
        <tr>
            @if ($logo)
                <td style="width: 160pt;"><img src="{{ $logo }}" class="logo" alt=""></td>
            @endif
            <td style="padding-right: 12pt;">
                <div class="empresa">{{ $empresa->nombre_comercial ?: $empresa->razon_social }}</div>
                @if ($empresa->nombre_comercial)
                    <div>{{ $empresa->razon_social }}</div>
                @endif
                <div class="muted" style="margin-top: 4pt;">
                    {{ $empresa->direccion }}<br>
                    {{ $empresa->distrito }} - {{ $empresa->provincia }} - {{ $empresa->departamento }}
                    @if ($empresa->email)<br>{{ $empresa->email }}@endif
                    @foreach ($c->informacionEn('cabecera') as $dato)
                        <div>@if ($dato['etiqueta'] ?? null)<span class="bold">{{ $dato['etiqueta'] }}:</span> @endif{{ $dato['valor'] }}</div>
                    @endforeach
                </div>
            </td>
            <td style="width: 175pt;">
                <div class="caja-numero">
                    <div class="ruc">R.U.C. {{ $empresa->ruc }}</div>
                    <div class="tipo">{{ $titulo }}</div>
                    <div class="numero">{{ $c->serie }} - {{ str_pad((string) $c->correlativo, 8, '0', STR_PAD_LEFT) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="cliente">
        <tr>
            <td style="width: 65%;">
                <span class="bold">{{ $c->tipo_comprobante->value === '01' ? 'Razón social' : 'Cliente' }}:</span> {{ $c->cliente_razon_social }}
            </td>
            <td><span class="bold">Fecha de emisión:</span> {{ $fecha->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>
                @if ($documentoCliente)
                    <span class="bold">{{ $documentoCliente }}:</span> {{ $c->cliente_numero_documento }}
                @endif
            </td>
            <td><span class="bold">Moneda:</span> {{ $c->moneda === 'PEN' ? 'SOLES' : $c->moneda }}</td>
        </tr>
        <tr>
            <td>
                @if ($c->cliente_direccion)
                    <span class="bold">Dirección:</span> {{ $c->cliente_direccion }}
                @endif
            </td>
            <td>
                @unless ($c->esNota())
                    <span class="bold">Forma de pago:</span> Contado
                @endunless
            </td>
        </tr>
        @if ($c->esNota() && $c->afectado)
            <tr>
                <td><span class="bold">Documento que modifica:</span> {{ $c->afectado->tipo_comprobante->nombre() }} {{ $c->afectado->numero() }}</td>
                <td><span class="bold">Motivo:</span> {{ $c->motivo_descripcion }}</td>
            </tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="right" style="width: 45pt;">Cant.</th>
                <th style="width: 35pt;">Unid.</th>
                <th style="width: 55pt;">Código</th>
                <th>Descripción</th>
                <th class="right" style="width: 60pt;">V. unit.</th>
                <th class="right" style="width: 60pt;">P. unit.</th>
                <th class="right" style="width: 65pt;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($c->items as $item)
                <tr>
                    <td class="right">{{ Decimal::compactar($item->cantidad) }}</td>
                    <td>{{ $item->unidad }}</td>
                    <td>{{ $item->codigo }}</td>
                    <td>{{ $item->descripcion }}</td>
                    <td class="right">{{ $precio(Decimal::compactar($item->valor_unitario)) }}</td>
                    <td class="right">{{ $precio(Decimal::compactar($item->precio_unitario)) }}</td>
                    <td class="right">{{ $monto($item->importe_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 10pt;">
        <tr>
            <td style="vertical-align: top; padding-right: 20pt;">
                <div class="letras"><span class="bold">{{ $c->monto_letras }}</span></div>
                @if ($c->detraccion)
                    <div class="letras">Operación sujeta al Sistema de Pago de Obligaciones Tributarias (detracción): {{ $c->detraccion["porcentaje"] }}% = S/ {{ $monto($c->detraccion["monto"]) }} · Bien o servicio {{ $c->detraccion["codigo"] }} · Cta. Banco de la Nación {{ $c->detraccion["cuenta"] }}</div>
                @endif
                @if ($c->observacion)
                    <div style="margin-top: 8pt;"><span class="bold">Observación:</span> {{ $c->observacion }}</div>
                @endif
                <div style="margin-top: 8pt;">
                    @foreach ($c->informacionEn('centro') as $dato)
                        <div>@if ($dato['etiqueta'] ?? null)<span class="bold">{{ $dato['etiqueta'] }}:</span> @endif{{ $dato['valor'] }}</div>
                    @endforeach
                </div>
            </td>
            <td style="width: 200pt; vertical-align: top;">
                <table class="totales">
                    <tr><td>Op. gravada</td><td class="right">S/ {{ $monto($c->op_gravadas) }}</td></tr>
                    @if ((float) $c->op_exoneradas > 0)
                        <tr><td>Op. exonerada</td><td class="right">S/ {{ $monto($c->op_exoneradas) }}</td></tr>
                    @endif
                    @if ((float) $c->op_inafectas > 0)
                        <tr><td>Op. inafecta</td><td class="right">S/ {{ $monto($c->op_inafectas) }}</td></tr>
                    @endif
                    @php($tasaIgv = rtrim(rtrim((string) ($c->items->firstWhere("tipo_afectacion_igv", "10")?->porcentaje_igv ?? "18"), "0"), "."))
                    <tr><td>IGV ({{ $tasaIgv }}%)</td><td class="right">S/ {{ $monto($c->igv) }}</td></tr>
                    <tr class="total"><td>TOTAL</td><td class="right">S/ {{ $monto($c->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="pie">
        <tr>
            <td style="width: 90pt;"><img src="{{ $qr }}" style="width: 80pt; height: 80pt;" alt=""></td>
            <td style="vertical-align: middle;">
                <div>Representación impresa de la <span class="bold">{{ $titulo }}</span>.</div>
                <div>Puede verificarla en <span class="bold">www.sunat.gob.pe</span></div>
                @if ($c->hash)
                    <div class="muted" style="margin-top: 4pt;">Resumen (hash): {{ $c->hash }}</div>
                @endif
                @foreach ($c->informacionEn('pie') as $dato)
                    <div>@if ($dato['etiqueta'] ?? null)<span class="bold">{{ $dato['etiqueta'] }}:</span> @endif{{ $dato['valor'] }}</div>
                @endforeach
            </td>
        </tr>
    </table>
</body>
</html>
