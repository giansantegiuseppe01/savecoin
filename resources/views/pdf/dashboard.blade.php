<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Financiero</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header-logo {
            max-height: 80px;
            max-width: 80px;
        }
        .header-right {
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .header h2 {
            margin: 0;
            font-size: 16px;
            color: #666;
            font-style: italic;
        }
        .header-info {
            margin-top: 10px;
            font-size: 11px;
            color: #666;
        }
        .header-info div {
            margin: 2px 0;
        }
        .report-title {
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
            text-align: right;
        }
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
        .ordenes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .ordenes-table th,
        .ordenes-table td {
            border: 1px solid #333;
            padding: 6px;
            text-align: left;
        }
        .ordenes-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .ordenes-table td.number {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
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

    <h3 class="report-title">Dashboard Financiero</h3>

    @if($filtros['fecha_inicio'] || $filtros['fecha_fin'] || $filtros['turno'] || $filtros['colaborador_id'])
    <div class="filtros">
        <strong>Filtros aplicados:</strong>
        @if($filtros['fecha_inicio']) Fecha Inicio: {{ \Carbon\Carbon::parse($filtros['fecha_inicio'])->format('d/m/Y') }} @endif
        @if($filtros['fecha_fin']) | Fecha Fin: {{ \Carbon\Carbon::parse($filtros['fecha_fin'])->format('d/m/Y') }} @endif
        @if($filtros['turno']) | Turno: {{ ucfirst($filtros['turno']) }} @endif
        @if($filtros['colaborador_id']) | Colaborador: #{{ $filtros['colaborador_id'] }} @endif
    </div>
    @endif

    <div class="resumen">
        <h3>Resumen Financiero</h3>
        <table>
            <tr>
                <td class="label">Ventas Brutas:</td>
                <td class="valor">${{ number_format($totales['ventasBrutas'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Reembolsos:</td>
                <td class="valor" style="color: red;">-${{ number_format($totales['reembolsos'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Descuentos:</td>
                <td class="valor" style="color: red;">-${{ number_format($totales['descuentos'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Ventas Netas:</td>
                <td class="valor">${{ number_format($totales['ventasNetas'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Costo de Insumos:</td>
                <td class="valor" style="color: red;">-${{ number_format($totales['costoInsumos'], 2) }}</td>
            </tr>
            <tr style="border-top: 2px solid #333;">
                <td class="label">Beneficio Bruto:</td>
                <td class="valor" style="font-size: 14px; color: green;">${{ number_format($totales['beneficioBruto'], 2) }}</td>
            </tr>
            @if($tasaCambio > 1)
            <tr>
                <td class="label">Tasa Cambio:</td>
                <td class="valor">{{ number_format($tasaCambio, 4) }} Bs/USD</td>
            </tr>
            <tr>
                <td class="label">Beneficio en Bs:</td>
                <td class="valor">{{ number_format($totales['beneficioBruto'] * $tasaCambio, 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    <h3>Detalle de Ventas ({{ $ordens->count() }} órdenes)</h3>
    <table class="ordenes-table">
        <thead>
            <tr>
                <th>Recibo</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Cajero</th>
                <th>Método</th>
                <th>Tipo</th>
                <th class="number">Subtotal</th>
                <th class="number">Descuento</th>
                <th class="number">Impuesto</th>
                <th class="number">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ordens as $orden)
            <tr>
                <td>{{ $orden->numero_recibo }}</td>
                <td>{{ $orden->fecha_hora->format('d/m/Y H:i') }}</td>
                <td>{{ $orden->cliente_nombre ?? ($orden->cliente ? $orden->cliente->nombre_completo : 'General') }}</td>
                <td>{{ $orden->user->name }}</td>
                <td>{{ ucfirst($orden->metodo_pago) }}</td>
                <td>{{ ucfirst($orden->tipo) }}</td>
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
