@extends('layouts.sidebar')

@section('title', 'Cuentas por Pagar - SaveCoin POS')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-receipt me-2"></i>Cuentas por Pagar</h2>
        <small class="text-muted">Facturas y préstamos que el negocio debe a proveedores o acreedores</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNueva">
        <i class="bi bi-plus-circle me-1"></i>Nueva cuenta
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@foreach(['pago', 'eliminar'] as $campo)
    @error($campo)
        <div class="alert alert-danger alert-dismissible fade show">{{ $message }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @enderror
@endforeach

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">Total que se debe</div>
                <div class="fs-3 fw-bold text-danger">${{ number_format($totalDebe, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">Vencidas</div>
                <div class="fs-3 fw-bold {{ $cantidadVencidas > 0 ? 'text-danger' : '' }}">{{ $cantidadVencidas }}</div>
                <div class="small text-muted">${{ number_format($montoVencido, 2) }} por vencer hace tiempo</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">Acreedores con deuda</div>
                <div class="fs-3 fw-bold">{{ $porAcreedor->count() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-people me-1"></i>¿A quién se le debe?</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Acreedor</th><th class="text-end">Cuentas</th><th class="text-end">Saldo</th></tr></thead>
                    <tbody>
                        @forelse($porAcreedor as $fila)
                            <tr>
                                <td>{{ $fila->acreedor }}</td>
                                <td class="text-end">{{ $fila->cuentas }}</td>
                                <td class="text-end fw-bold text-danger">${{ number_format($fila->saldo_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No hay deudas pendientes</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('cuentas-por-pagar.index') }}" class="row g-2 align-items-end mb-3">
                    <div class="col-md-5">
                        <label class="form-label small">Buscar</label>
                        <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="Acreedor, concepto o documento">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            @foreach(\App\Models\CuentaPorPagar::TIPOS as $valor => $nombre)
                                <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendientes</option>
                            <option value="vencida" @selected(request('estado') === 'vencida')>Vencidas</option>
                            <option value="pagada" @selected(request('estado') === 'pagada')>Pagadas</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-outline-secondary w-100" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table data-orden-servidor class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th data-orden="acreedor">Acreedor</th>
                                <th data-orden="tipo">Tipo</th>
                                <th>Concepto / Doc.</th>
                                <th data-orden="vence">Vence</th>
                                <th class="text-end" data-orden="monto">Monto</th>
                                <th class="text-end" data-orden="saldo">Saldo</th>
                                <th data-orden="estado">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cuentas as $cuenta)
                                @php $tienePagos = $cuenta->pagos->isNotEmpty(); @endphp
                                <tr>
                                    <td class="fw-bold">{{ $cuenta->acreedor }}</td>
                                    <td><span class="badge {{ $cuenta->tipo === 'prestamo' ? 'bg-info text-dark' : 'bg-secondary' }}">{{ \App\Models\CuentaPorPagar::TIPOS[$cuenta->tipo] ?? $cuenta->tipo }}</span></td>
                                    <td>
                                        {{ $cuenta->concepto ?? '-' }}
                                        @if($cuenta->numero_documento)<div class="small text-muted">Doc. {{ $cuenta->numero_documento }}</div>@endif
                                    </td>
                                    <td class="{{ $cuenta->esta_vencida ? 'text-danger fw-bold' : '' }}">
                                        {{ $cuenta->fecha_vencimiento?->format('d/m/Y') ?? '-' }}
                                    </td>
                                    <td class="text-end">${{ number_format($cuenta->monto, 2) }}</td>
                                    <td class="text-end fw-bold">${{ number_format($cuenta->saldo, 2) }}</td>
                                    <td>
                                        @if($cuenta->estado === 'pagada')
                                            <span class="badge bg-success">Pagada</span>
                                        @elseif($cuenta->esta_vencida)
                                            <span class="badge bg-danger">Vencida</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if($cuenta->estado === 'pendiente')
                                            <button class="btn btn-sm btn-success btn-action" title="Registrar pago"
                                                    onclick="abrirPagar({{ $cuenta->id }}, @js($cuenta->acreedor), {{ $cuenta->saldo }})">
                                                <i class="bi bi-cash-coin"></i>
                                            </button>
                                        @endif
                                        <button class="btn btn-sm btn-warning btn-action" title="Editar"
                                                onclick="abrirEditar(@js([
                                                    'id' => $cuenta->id,
                                                    'acreedor' => $cuenta->acreedor,
                                                    'tipo' => $cuenta->tipo,
                                                    'concepto' => $cuenta->concepto,
                                                    'numero_documento' => $cuenta->numero_documento,
                                                    'monto' => $cuenta->monto,
                                                    'fecha_emision' => $cuenta->fecha_emision?->format('Y-m-d'),
                                                    'fecha_vencimiento' => $cuenta->fecha_vencimiento?->format('Y-m-d'),
                                                    'notas' => $cuenta->notas,
                                                    'tienePagos' => $tienePagos,
                                                ]))">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('cuentas-por-pagar.destroy', $cuenta) }}" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar esta cuenta por pagar?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger btn-action" title="Eliminar" @disabled($tienePagos)>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">No hay cuentas que mostrar</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">{{ $cuentas->links() }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: nueva cuenta --}}
<div class="modal fade" id="modalNueva" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('cuentas-por-pagar.store') }}">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Nueva cuenta por pagar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('cuentas-por-pagar._campos', ['prefijo' => 'nueva', 'montoEditable' => true])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: editar cuenta --}}
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="formEditar">
                @csrf @method('PUT')
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Editar cuenta por pagar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('cuentas-por-pagar._campos', ['prefijo' => 'editar', 'montoEditable' => true])
                    <small class="text-muted d-block mt-2" id="editarAvisoMonto"></small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: registrar pago --}}
<div class="modal fade" id="modalPagar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formPagar">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Pagar a <span id="pagarAcreedor"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Saldo pendiente: <strong id="pagarSaldo"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Monto (USD) *</label>
                        <input type="number" name="monto" id="pagarMonto" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forma de pago *</label>
                        <select name="forma_pago" class="form-select" required>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="pago_movil">Pago móvil</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha del pago *</label>
                        <input type="date" name="fecha" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="referencia" class="form-control" maxlength="100" placeholder="Opcional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Registrar pago</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const RUTA_CUENTAS = @js(url('cuentas-por-pagar'));

function abrirEditar(c) {
    const f = document.getElementById('formEditar');
    f.action = `${RUTA_CUENTAS}/${c.id}`;
    const campos = {acreedor: c.acreedor, tipo: c.tipo, concepto: c.concepto ?? '', numero_documento: c.numero_documento ?? '',
        monto: c.monto, fecha_emision: c.fecha_emision, fecha_vencimiento: c.fecha_vencimiento ?? '', notas: c.notas ?? ''};
    Object.entries(campos).forEach(([nombre, valor]) => {
        const el = f.querySelector(`[name="${nombre}"]`);
        if (el) el.value = valor;
    });
    const monto = f.querySelector('[name="monto"]');
    monto.readOnly = c.tienePagos;
    document.getElementById('editarAvisoMonto').textContent = c.tienePagos
        ? 'Esta cuenta ya tiene pagos: el monto no se puede cambiar.' : '';
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function abrirPagar(id, acreedor, saldo) {
    const f = document.getElementById('formPagar');
    f.action = `${RUTA_CUENTAS}/${id}/pagos`;
    document.getElementById('pagarAcreedor').textContent = acreedor;
    document.getElementById('pagarSaldo').textContent = '$' + Number(saldo).toFixed(2);
    const monto = document.getElementById('pagarMonto');
    monto.max = saldo;
    monto.value = Number(saldo).toFixed(2);
    new bootstrap.Modal(document.getElementById('modalPagar')).show();
}
</script>
@endpush
