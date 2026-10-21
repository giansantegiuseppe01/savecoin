@extends('layouts.sidebar')

@section('title', 'Dashboard - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-speedometer2 me-2"></i>Dashboard Financiero</h1>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('dashboard.index') }}" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control"
                               value="{{ $fechaInicio->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control"
                               value="{{ $fechaFin->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Turno</label>
                        <select name="turno" class="form-select">
                            <option value="">Todos</option>
                            <option value="mañana" {{ $turno === 'mañana' ? 'selected' : '' }}>Mañana</option>
                            <option value="tarde" {{ $turno === 'tarde' ? 'selected' : '' }}>Tarde</option>
                            <option value="noche" {{ $turno === 'noche' ? 'selected' : '' }}>Noche</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Colaborador</label>
                        <select name="colaborador_id" class="form-select" id="colaboradorSelect">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                        <a href="{{ route('dashboard.pdf', request()->query()) }}" class="btn btn-success" target="_blank">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card kpi-card bg-primary">
            <i class="bi bi-cash-stack kpi-icon"></i>
            <h6 class="card-title">Ventas Brutas</h6>
            <div class="kpi-value">Bs {{ number_format($ventasBrutas, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card kpi-card bg-danger">
            <i class="bi bi-arrow-return-left kpi-icon"></i>
            <h6 class="card-title">Reembolsos</h6>
            <div class="kpi-value">Bs {{ number_format($reembolsos, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card kpi-card bg-warning">
            <i class="bi bi-percent kpi-icon"></i>
            <h6 class="card-title">Descuentos</h6>
            <div class="kpi-value">Bs {{ number_format($descuentos, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card kpi-card bg-success">
            <i class="bi bi-graph-up-arrow kpi-icon"></i>
            <h6 class="card-title">Beneficio Bruto</h6>
            <div class="kpi-value">Bs {{ number_format($beneficioBruto, 2) }}</div>
        </div>
    </div>
    <div class="col-md-12 col-lg-2 mb-3">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-graph-up-arrow me-1"></i>Beneficio Bruto</h6>
                <h3 class="card-text">${{ number_format($beneficioBruto, 2) }}</h3>
            </div>
        </div>
    </div>
</div>

<!-- Alertas de Stock -->
@if($stockAlerts['stock_bajo'] > 0)
<div class="row mb-4">
    <div class="col-12">
        <div class="card {{ $stockAlerts['stock_critico'] > 0 ? 'bg-danger' : 'bg-warning' }} text-white">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Alertas de Stock Bajo
                </h5>
                <p class="card-text mb-2">
                    <strong>{{ $stockAlerts['stock_bajo'] }}</strong> de {{ $stockAlerts['total_insumos'] }} insumos con stock bajo ({{ $stockAlerts['porcentaje_bajo'] }}%)
                    @if($stockAlerts['stock_critico'] > 0)
                        <span class="badge bg-white text-danger ms-2">{{ $stockAlerts['stock_critico'] }} críticos</span>
                    @endif
                </p>
                <div class="table-responsive">
                    <table class="table table-dark table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Insumo</th>
                                <th>Stock Actual</th>
                                <th>Stock Mínimo</th>
                                <th>Unidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stockBajoItems->take(5) as $insumo)
                            <tr>
                                <td>{{ $insumo->nombre }}</td>
                                <td class="{{ $insumo->stock_actual < ($insumo->stock_minimo * 0.5) ? 'text-danger fw-bold' : '' }}">
                                    {{ number_format($insumo->stock_actual, 2) }}
                                </td>
                                <td>{{ number_format($insumo->stock_minimo, 2) }}</td>
                                <td>{{ $insumo->unidad_medida }}</td>
                            </tr>
                            @endforeach
                            @if($stockBajoItems->count() > 5)
                            <tr>
                                <td colspan="4" class="text-center">
                                    <small>... y {{ $stockBajoItems->count() - 5 }} más</small>
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                <a href="{{ route('inventario.index') }}" class="btn btn-sm btn-light mt-2">
                    <i class="bi bi-box-seam me-1"></i>Ver Inventario
                </a>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Gráfica de Ventas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i>Tendencia de Ventas</h5>
            </div>
            <div class="card-body">
                <canvas id="ventasChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de Desglose Diario -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-table me-2"></i>Desglose Diario de Ventas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-end">Ventas Brutas (BS)</th>
                                <th class="text-end">Descuentos (BS)</th>
                                <th class="text-end">Ventas Netas (BS)</th>
                                <th class="text-end">Costo Insumos (BS)</th>
                                <th class="text-end">Beneficio (BS)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($desgloseDiario as $dia)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($dia->fecha)->format('d/m/Y') }}</td>
                                <td class="text-end">Bs {{ number_format($dia->bruto, 2) }}</td>
                                <td class="text-end text-danger">Bs {{ number_format($dia->descuento, 2) }}</td>
                                <td class="text-end fw-bold">Bs {{ number_format($dia->neto, 2) }}</td>
                                <td class="text-end">Bs {{ number_format($dia->costo_insumos, 2) }}</td>
                                <td class="text-end fw-bold {{ $dia->beneficio >= 0 ? 'text-success' : 'text-danger' }}">
                                    Bs {{ number_format($dia->beneficio, 2) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No hay datos disponibles para el período seleccionado</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th class="fw-bold">TOTAL</th>
                                <th class="text-end fw-bold">Bs {{ number_format($desgloseDiario->sum('bruto'), 2) }}</th>
                                <th class="text-end fw-bold">Bs {{ number_format($desgloseDiario->sum('descuento'), 2) }}</th>
                                <th class="text-end fw-bold">Bs {{ number_format($desgloseDiario->sum('neto'), 2) }}</th>
                                <th class="text-end fw-bold">Bs {{ number_format($desgloseDiario->sum('costo_insumos'), 2) }}</th>
                                <th class="text-end fw-bold">Bs {{ number_format($desgloseDiario->sum('beneficio'), 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cargar colaboradores
    fetch('/api/colaboradores')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('colaboradorSelect');
            data.forEach(colaborador => {
                const option = document.createElement('option');
                option.value = colaborador.id;
                option.textContent = colaborador.name;
                if (colaborador.id == '{{ $colaboradorId }}') {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        });

    // Gráfica de ventas
    const ctx = document.getElementById('ventasChart').getContext('2d');
    
    @php
        $fechas = $ventasPorDia->pluck('fecha')->map(function($fecha) {
            return \Carbon\Carbon::parse($fecha)->format('d/m');
        })->toArray();
        $brutos = $ventasPorDia->pluck('bruto')->toArray();
        $netos = $ventasPorDia->pluck('neto')->toArray();
        $beneficios = $ventasPorDia->pluck('beneficio')->toArray();
    @endphp

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {{ json_encode($fechas) }},
            datasets: [
                {
                    label: 'Ventas Netas',
                    data: {{ json_encode($netos) }},
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                },
                {
                    label: 'Beneficio Bruto',
                    data: {{ json_encode($beneficios) }},
                    borderColor: 'rgb(108, 117, 125)',
                    backgroundColor: 'rgba(108, 117, 125, 0.1)',
                    tension: 0.1,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': $' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush
