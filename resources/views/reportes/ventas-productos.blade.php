@extends('layouts.sidebar')

@section('title', 'Ventas por producto - SaveCoin POS')

@section('content')
@php
    $mismoDia = $inicio->isSameDay($fin);
    $fmt = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
@endphp
<div class="row mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-bar-chart-line me-2"></i>Ventas por producto</h1>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Imprimir
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('reportes.ventas-productos') }}" method="GET" class="row g-3">
            <div class="col-6 col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="fecha_inicio" class="form-control" value="{{ $inicio->toDateString() }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="fecha_fin" class="form-control" value="{{ $fin->toDateString() }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Producto</label>
                <select name="producto_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($productos as $p)
                        <option value="{{ $p->id }}" {{ (string) request('producto_id') === (string) $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Categoría</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categorias as $c)
                        <option value="{{ $c }}" {{ request('categoria') === $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Ver</label>
                <select name="agrupar" class="form-select">
                    <option value="total" {{ $agrupar === 'total' ? 'selected' : '' }}>Total del período</option>
                    <option value="dia" {{ $agrupar === 'dia' ? 'selected' : '' }}>Día por día</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button>
            </div>
            <div class="col-12">
                <a href="{{ route('reportes.ventas-productos', ['fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-primary">Hoy</a>
                <a href="{{ route('reportes.ventas-productos', ['fecha_inicio' => now()->subDay()->toDateString(), 'fecha_fin' => now()->subDay()->toDateString()]) }}" class="btn btn-sm btn-outline-primary">Ayer</a>
                <a href="{{ route('reportes.ventas-productos', ['fecha_inicio' => now()->subDays(6)->toDateString(), 'fecha_fin' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-primary">Últimos 7 días</a>
                <a href="{{ route('reportes.ventas-productos', ['fecha_inicio' => now()->startOfMonth()->toDateString(), 'fecha_fin' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-primary">Este mes</a>
            </div>
        </form>
    </div>
</div>

<p class="text-muted">
    {{ $mismoDia ? $inicio->format('d/m/Y') : $inicio->format('d/m/Y').' al '.$fin->format('d/m/Y') }}
    · Solo ventas cobradas (no incluye anuladas, eliminadas, reembolsos ni pedidos abiertos).
</p>

<!-- Resumen -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Unidades vendidas</div>
            <div class="fs-3 fw-bold">{{ $fmt($totalUnidades) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Ingresos (USD)</div>
            <div class="fs-3 fw-bold">${{ number_format($totalIngresos, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 border-success"><div class="card-body">
            <div class="text-muted small"><i class="bi bi-trophy text-warning"></i> Más vendido</div>
            <div class="fw-bold">{{ $masVendido->producto ?? '—' }}</div>
            @if($masVendido)<div class="small text-muted">{{ $fmt($masVendido->unidades) }} unidades</div>@endif
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Mayor ingreso</div>
            <div class="fw-bold">{{ $masIngresos->producto ?? '—' }}</div>
            @if($masIngresos)<div class="small text-muted">${{ number_format($masIngresos->ingresos, 2) }}</div>@endif
        </div></div>
    </div>
</div>

<!-- Detalle -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-list-ol me-2"></i>{{ $agrupar === 'dia' ? 'Detalle día por día' : 'Ranking de productos' }}
            <span class="text-muted fs-6">({{ $productosDistintos }} {{ $productosDistintos === 1 ? 'producto' : 'productos' }})</span>
        </h5>
    </div>
    <div class="card-body">
        @if($filas->isEmpty())
            <p class="text-muted mb-0">No hay ventas en este período con los filtros elegidos.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        @if($agrupar === 'dia')<th>Día</th>@endif
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th class="text-end">Cantidad</th>
                        <th style="min-width:140px"></th>
                        <th class="text-end">Ingresos (USD)</th>
                        <th class="text-end">% de unidades</th>
                        <th class="text-end">Recibos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($filas as $i => $f)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        @if($agrupar === 'dia')<td>{{ \Carbon\Carbon::parse($f->dia)->format('d/m/Y') }}</td>@endif
                        <td><strong>{{ $f->producto }}</strong></td>
                        <td>{{ $f->categoria ?: '—' }}</td>
                        <td class="text-end fw-bold">{{ $fmt($f->unidades) }}</td>
                        <td>
                            <div class="progress" style="height:8px">
                                <div class="progress-bar bg-success" style="width: {{ round($f->unidades / $maxUnidades * 100) }}%"></div>
                            </div>
                        </td>
                        <td class="text-end">${{ number_format($f->ingresos, 2) }}</td>
                        <td class="text-end">{{ $totalUnidades > 0 ? number_format($f->unidades / $totalUnidades * 100, 1) : 0 }}%</td>
                        <td class="text-end">{{ $f->recibos }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-active fw-bold">
                        <td colspan="{{ $agrupar === 'dia' ? 4 : 3 }}">Total</td>
                        <td class="text-end">{{ $fmt($totalUnidades) }}</td>
                        <td></td>
                        <td class="text-end">${{ number_format($totalIngresos, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
