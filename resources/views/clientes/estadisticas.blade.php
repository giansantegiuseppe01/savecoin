@extends('layouts.sidebar')

@section('title', 'Estadísticas de Clientes - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-graph-up me-2"></i>Estadísticas de Clientes</h1>
        
        <!-- Cliente del Mes -->
        <div class="card mb-4 bg-gradient-warning">
            <div class="card-body text-white">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-1"><i class="bi bi-trophy me-2"></i>Cliente del Mes</h5>
                        @if($clienteDelMes)
                            <h2 class="display-4 fw-bold">{{ $clienteDelMes->nombre_completo }}</h2>
                            <p class="mb-0">
                                <i class="bi bi-cart-check me-1"></i>{{ $clienteDelMes->ordens_count }} órdenes este mes
                            </p>
                        @else
                            <h4 class="fw-bold">No hay datos para este mes</h4>
                        @endif
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="{{ route('clientes.index') }}" class="btn btn-light btn-lg">
                            <i class="bi bi-arrow-left me-2"></i>Volver a Clientes
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 10 Clientes -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-list-ol me-2"></i>Top 10 Clientes por Número de Órdenes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre Completo</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th class="text-end">Total Órdenes</th>
                                <th class="text-end">Total Gastado (USD)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($topClientes->count() > 0)
                                @foreach($topClientes as $index => $cliente)
                                <tr>
                                    <td>
                                        @if($index === 0)
                                            <span class="badge bg-warning text-dark fs-6">🥇</span>
                                        @elseif($index === 1)
                                            <span class="badge bg-secondary text-dark fs-6">🥈</span>
                                        @elseif($index === 2)
                                            <span class="badge bg-danger text-dark fs-6">🥉</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ $index + 1 }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $cliente->nombre_completo }}</strong>
                                    </td>
                                    <td>{{ $cliente->cedula }}</td>
                                    <td>{{ $cliente->telefono ?? '-' }}</td>
                                    <td class="text-end">
                                        <span class="badge bg-primary fs-6">{{ $cliente->ordens_count }}</span>
                                    </td>
                                    <td class="text-end fw-bold">${{ number_format($cliente->total_gastado, 2) }}</td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-graph-up fs-1 d-block mb-2"></i>
                                            <h5>No hay datos disponibles</h5>
                                            <p>No hay clientes con órdenes registradas</p>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
