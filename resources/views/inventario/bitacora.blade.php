@extends('layouts.sidebar')

@section('title', 'Bitácora de Inventario - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-journal-text me-2"></i>Bitácora de Inventario</h1>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('inventario.bitacora') }}" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Insumo</label>
                        <select name="insumo_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach($insumos as $id => $nombre)
                            <option value="{{ $id }}" {{ request('insumo_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo Movimiento</label>
                        <select name="tipo_movimiento" class="form-select">
                            <option value="">Todos</option>
                            <option value="entrada" {{ request('tipo_movimiento') === 'entrada' ? 'selected' : '' }}>Entrada</option>
                            <option value="salida" {{ request('tipo_movimiento') === 'salida' ? 'selected' : '' }}>Salida</option>
                            <option value="ajuste" {{ request('tipo_movimiento') === 'ajuste' ? 'selected' : '' }}>Ajuste</option>
                            <option value="reembolso" {{ request('tipo_movimiento') === 'reembolso' ? 'selected' : '' }}>Reembolso</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" 
                               value="{{ request('fecha_inicio') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" 
                               value="{{ request('fecha_fin') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Bitácora -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Historial de Movimientos</h5>
                <a href="{{ route('inventario.bitacora-pdf', request()->query()) }}" class="btn btn-success" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Descargar PDF
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Insumo</th>
                                <th>Tipo Movimiento</th>
                                <th class="text-end">Anterior</th>
                                <th class="text-end">Movimiento</th>
                                <th class="text-end">Nuevo</th>
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
                                    <br><small class="text-muted">{{ $movimiento->insumo->codigo }}</small>
                                </td>
                                <td>
                                    @if($movimiento->tipo_movimiento === 'entrada')
                                        <span class="badge bg-success">Entrada</span>
                                    @elseif($movimiento->tipo_movimiento === 'salida')
                                        <span class="badge bg-danger">Salida</span>
                                    @elseif($movimiento->tipo_movimiento === 'ajuste')
                                        <span class="badge bg-info">Ajuste</span>
                                    @elseif($movimiento->tipo_movimiento === 'reembolso')
                                        <span class="badge bg-warning">Reembolso</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($movimiento->cantidad_anterior, 0) }}</td>
                                <td class="text-end">
                                    <span class="{{ $movimiento->tipo_movimiento === 'salida' || $movimiento->tipo_movimiento === 'reembolso' ? 'text-danger' : 'text-success' }}">
                                        {{ $movimiento->tipo_movimiento === 'salida' || $movimiento->tipo_movimiento === 'reembolso' ? '-' : '+' }}
                                        {{ number_format($movimiento->cantidad_movimiento, 0) }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold">{{ number_format($movimiento->cantidad_nueva, 0) }}</td>
                                <td>
                                    <small>{{ Str::limit($movimiento->motivo, 40) }}</small>
                                </td>
                                <td>
                                    <small>{{ $movimiento->user->name ?? 'Sistema' }}</small>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        Mostrando {{ $movimientos->firstItem() }} a {{ $movimientos->lastItem() }} 
                        de {{ $movimientos->total() }} registros
                    </div>
                    {{ $movimientos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
