@extends('layouts.sidebar')

@section('title', 'Recibos - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-receipt me-2"></i>Gestión de Recibos</h1>
        
        <!-- KPIs -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-receipt me-1"></i>Total Recibos</h6>
                        <h2 class="card-text">{{ $totalRecibos }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-check-circle me-1"></i>Ventas Activas</h6>
                        <h2 class="card-text">{{ $ventasActivas }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-arrow-return-left me-1"></i>Reembolsos</h6>
                        <h2 class="card-text">{{ $reembolsos }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('recibos.index') }}" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="venta" {{ request('tipo') === 'venta' ? 'selected' : '' }}>Venta</option>
                            <option value="reembolso" {{ request('tipo') === 'reembolso' ? 'selected' : '' }}>Reembolso</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="completada" {{ request('estado') === 'completada' ? 'selected' : '' }}>Completada</option>
                            <option value="anulada" {{ request('estado') === 'anulada' ? 'selected' : '' }}>Anulada</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Recibos -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Listado de Recibos</h5>
                <a href="{{ route('recibos.pdf-list', request()->query()) }}" class="btn btn-success" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Descargar PDF
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Nº Recibo</th>
                                <th>Fecha/Hora</th>
                                <th>Empleado</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ordens as $orden)
                            <tr class="{{ $orden->estado === 'anulada' ? 'table-danger' : '' }}">
                                <td>
                                    <strong>{{ $orden->numero_recibo }}</strong>
                                </td>
                                <td>{{ $orden->fecha_hora->format('d/m/Y H:i') }}</td>
                                <td>{{ $orden->user->name ?? 'N/A' }}</td>
                                <td>{{ $orden->cliente_nombre ?? 'General' }}</td>
                                <td>
                                    @if($orden->tipo === 'venta')
                                        <span class="badge bg-success">Venta</span>
                                    @else
                                        <span class="badge bg-danger">Reembolso</span>
                                    @endif
                                </td>
                                <td>
                                    @if($orden->estado === 'completada')
                                        <span class="badge bg-primary">Completada</span>
                                    @elseif($orden->estado === 'anulada')
                                        <span class="badge bg-danger">Anulada</span>
                                    @else
                                        <span class="badge bg-warning">{{ $orden->estado }}</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">
                                    <span class="{{ $orden->tipo === 'reembolso' ? 'text-danger' : '' }}">
                                        Bs {{ number_format($orden->total * ($orden->tasa_cambio ?? 1), 2) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('recibos.show', $orden->id) }}" 
                                           class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($orden->tipo === 'venta' && $orden->estado === 'completada')
                                            <form action="{{ route('recibos.anular', $orden->id) }}" 
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        title="Anular Recibo"
                                                        onclick="return confirm('¿Está seguro de anular este recibo? Se revertirá el stock de insumos y se actualizará la bitácora de inventario.')">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        Mostrando {{ $ordens->firstItem() }} a {{ $ordens->lastItem() }} 
                        de {{ $ordens->total() }} registros
                    </div>
                    {{ $ordens->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
