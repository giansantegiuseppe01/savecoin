<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bitácora de Inventario</title>
    <link rel="stylesheet" href="{{ asset('css/reportes-pdf.css') }}">
    <style>
        .report-title {
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
        .bitacora-table {
            font-size: 10px;
        }
        .tipo-entrada {
            background-color: #d4edda;
        }
        .tipo-salida {
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

    <h3 class="report-title">Bitácora de Inventario</h3>

    @if($filtros['insumo_id'] || $filtros['tipo_movimiento'] || $filtros['fecha_inicio'] || $filtros['fecha_fin'])
    <div class="filtros">
        <strong>Filtros aplicados:</strong>
        @if($filtros['insumo_id']) Insumo: {{ $movimientos->first()?->insumo?->nombre ?? '-' }} @endif
        @if($filtros['tipo_movimiento']) | Tipo: {{ ucfirst($filtros['tipo_movimiento']) }} @endif
        @if($filtros['fecha_inicio']) | Fecha Inicio: {{ \Carbon\Carbon::parse($filtros['fecha_inicio'])->format('d/m/Y') }} @endif
        @if($filtros['fecha_fin']) | Fecha Fin: {{ \Carbon\Carbon::parse($filtros['fecha_fin'])->format('d/m/Y') }} @endif
    </div>
    @endif

    <div class="resumen">
        <h3>Resumen</h3>
        <table>
            <tr>
                <td class="label">Total Movimientos:</td>
                <td class="valor">{{ $totalMovimientos }}</td>
            </tr>
            <tr>
                <td class="label">Total Entradas:</td>
                <td class="valor" style="color: green;">{{ number_format($totalEntradas, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total Salidas:</td>
                <td class="valor" style="color: red;">{{ number_format($totalSalidas, 2) }}</td>
            </tr>
        </table>
    </div>

    <h3>Movimientos ({{ $movimientos->count() }} registros)</h3>
    <table class="table bitacora-table">
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Insumo</th>
                <th>Tipo</th>
                <th class="number">Anterior</th>
                <th class="number">Movimiento</th>
                <th class="number">Nuevo</th>
                <th>Motivo</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movimientos as $movimiento)
            <tr>
                <td>{{ $movimiento->created_at->format('d/m/Y H:i:s') }}</td>
                <td>
                    <strong>{{ $movimiento->insumo->nombre }}</strong>
                    <br><small>{{ $movimiento->insumo->codigo }}</small>
                </td>
                <td>
                    @if($movimiento->tipo_movimiento === 'entrada')
                        <span style="color: green;">Entrada</span>
                    @elseif($movimiento->tipo_movimiento === 'salida')
                        <span style="color: red;">Salida</span>
                    @elseif($movimiento->tipo_movimiento === 'ajuste')
                        <span style="color: blue;">Ajuste</span>
                    @else
                        <span style="color: orange;">Reembolso</span>
                    @endif
                </td>
                <td class="number">{{ number_format($movimiento->cantidad_anterior, 2) }}</td>
                <td class="number">{{ number_format($movimiento->cantidad_movimiento, 2) }}</td>
                <td class="number">{{ number_format($movimiento->cantidad_nueva, 2) }}</td>
                <td>{{ $movimiento->motivo }}</td>
                <td>{{ $movimiento->user?->name ?? '-' }}</td>
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
