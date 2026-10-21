@extends('layouts.sidebar')

@section('title', 'Recibo #' . $orden->numero_recibo . ' - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">
                <i class="bi bi-receipt me-2"></i>Recibo #{{ $orden->numero_recibo }}
            </h1>
            <a href="{{ route('recibos.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>

        <!-- Información General -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Información del Recibo</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th>Número de Recibo:</th>
                                <td><strong>{{ $orden->numero_recibo }}</strong></td>
                            </tr>
                            <tr>
                                <th>Fecha y Hora:</th>
                                <td>{{ $orden->fecha_hora->format('d/m/Y H:i:s') }}</td>
                            </tr>
                            <tr>
                                <th>Empleado:</th>
                                <td>{{ $orden->user->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Cliente:</th>
                                <td>
                                    @if($orden->cliente)
                                        <strong>{{ $orden->cliente->nombre_completo }}</strong>
                                        <br><small class="text-muted">C.I: {{ $orden->cliente->cedula }}</small>
                                    @else
                                        General
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Tipo:</th>
                                <td>
                                    @if($orden->tipo === 'venta')
                                        <span class="badge bg-success">Venta</span>
                                    @else
                                        <span class="badge bg-danger">Reembolso</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Estado:</th>
                                <td>
                                    @if($orden->estado === 'completada')
                                        <span class="badge bg-primary">Completada</span>
                                    @elseif($orden->estado === 'anulada')
                                        <span class="badge bg-danger">Anulada</span>
                                    @else
                                        <span class="badge bg-warning">{{ $orden->estado }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Método de Pago:</th>
                                <td>{{ ucfirst($orden->metodo_pago) ?? 'N/A' }}</td>
                            </tr>
                            @if($orden->referencia_pago)
                            <tr>
                                <th>Referencia:</th>
                                <td>{{ $orden->referencia_pago }}</td>
                            </tr>
                            @endif
                            <tr>
                                <th>Turno:</th>
                                <td>{{ ucfirst($orden->turno) ?? 'N/A' }}</td>
                            </tr>
                            @if($orden->recibo_anulado_id)
                            <tr>
                                <th>Recibo Anulado:</th>
                                <td>{{ $orden->recibo_anulado_id }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-cash-stack me-2"></i>Resumen Financiero</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th>Subtotal (USD):</th>
                                <td class="text-end">${{ number_format($orden->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <th>Descuento (USD):</th>
                                <td class="text-end text-danger">-${{ number_format($orden->descuento, 2) }}</td>
                            </tr>
                            <tr>
                                <th>Impuesto (USD):</th>
                                <td class="text-end">${{ number_format($orden->impuesto, 2) }}</td>
                            </tr>
                            <tr class="table-active">
                                <th class="fw-bold">Total (USD):</th>
                                <td class="text-end fw-bold fs-5 {{ $orden->tipo === 'reembolso' ? 'text-danger' : '' }}">
                                    ${{ number_format($orden->total, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <th>Tasa de Cambio:</th>
                                <td class="text-end">1 USD = {{ number_format($orden->tasa_cambio ?? 1, 4) }} BS</td>
                            </tr>
                            <tr class="table-success">
                                <th class="fw-bold">Total (BS):</th>
                                <td class="text-end fw-bold fs-4 {{ $orden->tipo === 'reembolso' ? 'text-danger' : '' }}">
                                    Bs {{ number_format($orden->total * ($orden->tasa_cambio ?? 1), 2) }}
                                </td>
                            </tr>
                            <tr>
                                <th>Costo de Insumos (USD):</th>
                                <td class="text-end">${{ number_format($orden->costo_insumos, 2) }}</td>
                            </tr>
                            <tr class="table-active">
                                <th class="fw-bold">Beneficio Bruto (USD):</th>
                                <td class="text-end fw-bold fs-5 {{ $orden->beneficio_bruto >= 0 ? 'text-success' : 'text-danger' }}">
                                    ${{ number_format($orden->beneficio_bruto, 2) }}
                                </td>
                            </tr>
                            <tr class="table-success">
                                <th class="fw-bold">Beneficio Bruto (BS):</th>
                                <td class="text-end fw-bold fs-5 {{ $orden->beneficio_bruto >= 0 ? 'text-success' : 'text-danger' }}">
                                    Bs {{ number_format($orden->beneficio_bruto * ($orden->tasa_cambio ?? 1), 2) }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalles de la Orden -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><i class="bi bi-list-check me-2"></i>Detalles de la Orden</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Unitario</th>
                                <th class="text-end">Descuento</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orden->detalles as $detalle)
                            <tr>
                                <td>
                                    <strong>{{ $detalle->producto->nombre }}</strong>
                                    @if($detalle->notas)
                                    <br><small class="text-muted">{{ $detalle->notas }}</small>
                                    @endif
                                </td>
                                <td class="text-center">{{ $detalle->cantidad }}</td>
                                <td class="text-end">${{ number_format($detalle->precio_unitario, 2) }}</td>
                                <td class="text-end text-danger">
                                    @if($detalle->descuento > 0)
                                        -${{ number_format($detalle->descuento, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end fw-bold">${{ number_format($detalle->total, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-active">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">TOTAL:</td>
                                <td class="text-end fw-bold fs-5">
                                    ${{ number_format($orden->detalles->sum('total'), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Notas -->
        @if($orden->notas)
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0"><i class="bi bi-sticky me-2"></i>Notas</h5>
            </div>
            <div class="card-body">
                <p class="mb-0">{{ $orden->notas }}</p>
            </div>
        </div>
        @endif

        <!-- Acciones -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex gap-2">
                    <a href="{{ route('recibos.pdf-single', $orden->id) }}" class="btn btn-primary" target="_blank">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Descargar PDF
                    </a>
                    @if($orden->tipo === 'venta' && $orden->estado === 'completada')
                    <form action="{{ route('recibos.anular', $orden->id) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-danger"
                                onclick="return confirm('¿Está seguro de anular este recibo? Esta acción:\n• Marcará el recibo como anulado\n• Revertirá el stock de insumos automáticamente\n• Registrará el movimiento en la bitácora de inventario\n• Restará el monto de la ganancia del día')">
                            <i class="bi bi-x-circle me-1"></i>Anular Recibo
                        </button>
                    </form>
                    <form action="{{ route('recibos.reembolso', $orden->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning"
                                onclick="return confirm('¿Crear reembolso? Esto creará un recibo de reembolso negativo y anulará el recibo original.')">
                            <i class="bi bi-arrow-return-left me-1"></i>Crear Reembolso
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
