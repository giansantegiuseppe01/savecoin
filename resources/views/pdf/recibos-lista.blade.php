<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recibos</title>
    <link rel="stylesheet" href="{{ asset('css/reportes-pdf.css') }}">
    <style>
        .filtros {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .resumen {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .resumen table {
            width: 100%;
        }
        .resumen td {
            padding: 5px;
        }
        .resumen td.label {
            font-weight: bold;
            width: 200px;
        }
        .resumen td.valor {
            text-align: right;
            font-weight: bold;
        }
        .recibos-table {
            font-size: 10px;
        }
        .anulado {
            background-color: #f8d7da;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>{{ $config->nombre_empresa ?? 'SaveCoin Restaurant' }}</h1>
            <h2>{{ $config->eslogan ?? '' }}</h2>
            <div class="header-info">
                @if($config->rif)<div><strong>RIF:</strong> {{ $config->rif }}</div>@endif
                @if($config->telefono)<div><strong>Teléfono:</strong> {{ $config->telefono }}</div>@endif
                @if($config->email)<div><strong>Email:</strong> {{ $config->email }}</div>@endif
            </div>
        </div>
        <div class="header-right">
            @if($config && $config->logo)
            <img src="{{ public_path($config->logo) }}" alt="Logo" class="header-logo">
            @endif
        </div>
    </div>

    <h3 class="report-title">Reporte de Recibos</h3>

    @if($filtros['tipo'] || $filtros['estado'])
    <div class="filtros">
        <strong>Filtros aplicados:</strong>
        @if($filtros['tipo']) Tipo: {{ ucfirst($filtros['tipo']) }} @endif
        @if($filtros['estado']) | Estado: {{ ucfirst($filtros['estado']) }} @endif
    </div>
    @endif

    <div class="resumen">
        <h3>Resumen Financiero</h3>
        <table>
            <tr>
                <td class="label">Ventas Brutas:</td>
                <td class="valor">${{ number_format($ventasBrutas, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Reembolsos:</td>
                <td class="valor" style="color: red;">-${{ number_format($reembolsos, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Descuentos:</td>
                <td class="valor" style="color: red;">-${{ number_format($descuentos, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Ventas Netas:</td>
                <td class="valor">${{ number_format($ventasNetas, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Costo de Insumos:</td>
                <td class="valor" style="color: red;">-${{ number_format($costoInsumos, 2) }}</td>
            </tr>
            <tr style="border-top: 2px solid #333;">
                <td class="label">Beneficio Bruto:</td>
                <td class="valor" style="font-size: 14px; color: green;">${{ number_format($beneficioBruto, 2) }}</td>
            </tr>
            @if($tasaCambio > 1)
            <tr>
                <td class="label">Tasa Cambio:</td>
                <td class="valor">{{ number_format($tasaCambio, 4) }} Bs/USD</td>
            </tr>
            <tr>
                <td class="label">Beneficio en Bs:</td>
                <td class="valor">{{ number_format($beneficioBruto * $tasaCambio, 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    <h3>Detalle de Recibos ({{ $ordens->count() }} registros)</h3>
    <table class="table recibos-table">
        <thead>
            <tr>
                <th>Recibo</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Cajero</th>
                <th>Método</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th class="number">Subtotal</th>
                <th class="number">Descuento</th>
                <th class="number">Impuesto</th>
                <th class="number">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ordens as $orden)
            <tr class="{{ $orden->estado === 'anulada' ? 'anulado' : '' }}">
                <td>{{ $orden->numero_recibo }}</td>
                <td>{{ $orden->fecha_hora->format('d/m/Y H:i') }}</td>
                <td>{{ $orden->cliente_nombre ?? ($orden->cliente ? $orden->cliente->nombre_completo : 'General') }}</td>
                <td>{{ $orden->user->name }}</td>
                <td>{{ ucfirst($orden->metodo_pago) }}</td>
                <td>{{ ucfirst($orden->tipo) }}</td>
                <td>{{ ucfirst($orden->estado) }}</td>
                <td class="number">${{ number_format($orden->subtotal, 2) }}</td>
                <td class="number">${{ number_format($orden->descuento, 2) }}</td>
                <td class="number">${{ number_format($orden->impuesto, 2) }}</td>
                <td class="number">${{ number_format($orden->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado el: {{ now()->format('d/m/Y H:i') }}</p>
        <p>{{ $config->nombre_empresa ?? 'SaveCoin Restaurant' }}</p>
    </div>
</body>
</html>
