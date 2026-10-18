<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recibo #{{ $orden->numero_recibo }}</title>
    <link rel="stylesheet" href="{{ asset('css/reportes-pdf.css') }}">
    <style>
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 5px;
        }
        .info-table td.label {
            font-weight: bold;
            width: 150px;
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

    <table class="info-table">
        <tr>
            <td class="label">Recibo #:</td>
            <td>{{ $orden->numero_recibo }}</td>
        </tr>
        <tr>
            <td class="label">Fecha:</td>
            <td>{{ $orden->fecha_hora->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Cliente:</td>
            <td>{{ $orden->cliente_nombre ?? ($orden->cliente ? $orden->cliente->nombre_completo : 'General') }}</td>
        </tr>
        <tr>
            <td class="label">Método Pago:</td>
            <td>{{ ucfirst($orden->metodo_pago) }}</td>
        </tr>
        <tr>
            <td class="label">Cajero:</td>
            <td>{{ $orden->user->name }}</td>
        </tr>
    </table>

    <h3>Detalles de la Orden</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Precio Unit.</th>
                <th>Descuento</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orden->detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto_nombre }}</td>
                <td>{{ $detalle->cantidad }}</td>
                <td>${{ number_format($detalle->precio_unitario, 2) }}</td>
                <td>${{ number_format($detalle->descuento, 2) }}</td>
                <td>${{ number_format($detalle->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        <div>Subtotal: ${{ number_format($orden->subtotal, 2) }}</div>
        @if($orden->descuento > 0)
        <div>Descuento: ${{ number_format($orden->descuento, 2) }}</div>
        @endif
        @if($orden->aplica_iva)
        <div>IVA (16%): ${{ number_format($orden->impuesto, 2) }}</div>
        @endif
        <div class="total-final">
            Total: ${{ number_format($orden->total, 2) }}
        </div>
        @if($tasaCambio > 1)
        <div style="margin-top: 10px; font-size: 11px;">
            Tasa: {{ number_format($tasaCambio, 4) }} Bs/USD<br>
            Total en Bs: {{ number_format($orden->total * $tasaCambio, 2) }}
        </div>
        @endif
    </div>

    @if($orden->notas)
    <div style="margin-top: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd;">
        <strong>Notas:</strong>
        <p>{{ $orden->notas }}</p>
    </div>
    @endif

    <div class="footer">
        <p>¡Gracias por su compra!</p>
        <p>Generado el: {{ now()->format('d/m/Y H:i') }}</p>
        <p>{{ $config->nombre_empresa ?? 'SaveCoin Restaurant' }}</p>
    </div>
</body>
</html>
