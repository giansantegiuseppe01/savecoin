<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventario</title>
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
            text-align: center;
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
        .inventario-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .inventario-table th,
        .inventario-table td {
            border: 1px solid #333;
            padding: 6px;
            text-align: left;
        }
        .inventario-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .inventario-table td.number {
            text-align: right;
        }
        .stock-bajo {
            background-color: #fff3cd;
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

    <h3 class="report-title">Inventario</h3>

    @if($filtros['buscar'] || $filtros['estado'])
    <div class="filtros">
        <strong>Filtros aplicados:</strong>
        @if($filtros['buscar']) Búsqueda: {{ $filtros['buscar'] }} @endif
        @if($filtros['estado']) | Estado: {{ ucfirst($filtros['estado']) }} @endif
    </div>
    @endif

    <div class="resumen">
        <h3>Resumen</h3>
        <table>
            <tr>
                <td class="label">Total Items:</td>
                <td class="valor">{{ $totalItems }}</td>
            </tr>
            <tr>
                <td class="label">Valor Total:</td>
                <td class="valor">${{ number_format($valorTotal, 2) }}</td>
            </tr>
        </table>
    </div>

    <h3>Listado de Insumos ({{ $insumos->count() }} registros)</h3>
    <table class="inventario-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Unidad</th>
                <th class="number">Stock Actual</th>
                <th class="number">Stock Mínimo</th>
                <th class="number">Costo Unitario</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($insumos as $insumo)
            <tr class="{{ $insumo->stock_actual < $insumo->stock_minimo ? 'stock-bajo' : '' }}">
                <td>{{ $insumo->codigo }}</td>
                <td>{{ $insumo->nombre }}</td>
                <td>{{ $insumo->unidad_medida }}</td>
                <td class="number">{{ number_format($insumo->stock_actual, 2) }}</td>
                <td class="number">{{ number_format($insumo->stock_minimo, 2) }}</td>
                <td class="number">${{ number_format($insumo->costo_unitario, 2) }}</td>
                <td>{{ $insumo->activo ? 'Activo' : 'Inactivo' }}</td>
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
