@extends('layouts.sidebar')

@section('title', 'Clientes - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-people me-2"></i>Gestión de Clientes</h1>
        
        <!-- KPIs -->
        <div class="row mb-4">
            <div class="col-6 col-lg-3 mb-3">
                <div class="card kpi-card bg-primary">
                    <i class="bi bi-people kpi-icon"></i>
                    <h6 class="card-title">Total Clientes</h6>
                    <div class="kpi-value">{{ $clientes->total() }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="card kpi-card bg-success">
                    <i class="bi bi-cash-coin kpi-icon"></i>
                    <h6 class="card-title">Pagado (USD)</h6>
                    <div class="kpi-value">${{ number_format($totalPagado, 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="card kpi-card bg-danger">
                    <i class="bi bi-hourglass-split kpi-icon"></i>
                    <h6 class="card-title">Por cobrar (USD)</h6>
                    <div class="kpi-value">${{ number_format($totalPorCobrar, 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="card kpi-card bg-warning">
                    <i class="bi bi-receipt kpi-icon"></i>
                    <h6 class="card-title">Clientes que deben</h6>
                    <div class="kpi-value">{{ $clientesQueDeben }}</div>
                    <small>{{ $ticketsAbiertos }} {{ $ticketsAbiertos === 1 ? 'ticket abierto' : 'tickets abiertos' }}</small>
                </div>
            </div>
        </div>

        <!-- Filtros y Acciones -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('clientes.index') }}" method="GET" class="row g-3">
                    <div class="col-md-7">
                        <input type="text" name="buscar" class="form-control" 
                               placeholder="Buscar por nombre, apellido, cédula, teléfono o correo..." 
                               value="{{ request('buscar') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="con_deuda" value="1" id="conDeuda" {{ request()->boolean('con_deuda') ? 'checked' : '' }}>
                            <label class="form-check-label" for="conDeuda">Solo los que deben</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Clientes -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Listado de Clientes</h5>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#crearClienteModal">
                        <i class="bi bi-plus-circle me-1"></i>Nuevo Cliente
                    </button>
                    <a href="{{ route('clientes.estadisticas') }}" class="btn btn-info ms-2">
                        <i class="bi bi-graph-up me-1"></i>Estadísticas
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th class="text-end">Órdenes</th>
                                <th class="text-end">Pagado (USD)</th>
                                <th class="text-end">Debe (USD)</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($clientes->count() > 0)
                                @foreach($clientes as $cliente)
                                @php
                                    $ticketsDeCliente = $cliente->ordens->map(fn ($o) => [
                                        'id' => $o->id,
                                        'recibo' => $o->numero_recibo,
                                        'fecha' => optional($o->fecha_hora)->format('d/m/Y H:i'),
                                        'total' => (float) $o->total,
                                    ])->values();
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $cliente->nombre_completo }}</strong>
                                    </td>
                                    <td>{{ $cliente->cedula }}</td>
                                    <td>{{ $cliente->telefono ?? '-' }}</td>
                                    <td>{{ $cliente->correo ?? '-' }}</td>
                                    <td class="text-end">
                                        <span class="badge bg-primary">{{ $cliente->total_ordenes }}</span>
                                    </td>
                                    <td class="text-end fw-bold">${{ number_format($cliente->total_gastado, 2) }}</td>
                                    <td class="text-end">
                                        @if($cliente->deuda > 0)
                                            <button type="button" class="btn btn-sm btn-danger fw-bold"
                                                    onclick="verTicketsAbiertos(@js($cliente->nombre_completo), @js($ticketsDeCliente))"
                                                    title="Ver tickets abiertos">
                                                ${{ number_format($cliente->deuda, 2) }}
                                                <span class="badge bg-light text-danger ms-1">{{ $cliente->tickets_abiertos_count }}</span>
                                            </button>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-warning btn-action" 
                                                    onclick="abrirModalEditar({{ $cliente->id }}, @js($cliente->nombre), @js($cliente->apellido), @js($cliente->cedula), @js($cliente->telefono ?? ''), @js($cliente->correo ?? ''))"
                                                    title="Editar Cliente">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-action" 
                                                    onclick="eliminarCliente({{ $cliente->id }}, @js($cliente->nombre_completo))"
                                                    title="Eliminar Cliente">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-people fs-1 d-block mb-2"></i>
                                            <h5>No hay clientes registrados</h5>
                                            <p>Comienza agregando un nuevo cliente usando el botón "Nuevo Cliente"</p>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        Mostrando {{ $clientes->firstItem() }} a {{ $clientes->lastItem() }} 
                        de {{ $clientes->total() }} registros
                    </div>
                    {{ $clientes->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Cliente -->
<div class="modal fade" id="crearClienteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCrearCliente" action="{{ route('clientes.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido *</label>
                        <input type="text" name="apellido" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula *</label>
                        <input type="text" name="cedula" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Crear Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Cliente -->
<div class="modal fade" id="editarClienteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
           <form id="formEditarCliente" action="" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="id" id="editarClienteId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" id="editarNombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido *</label>
                        <input type="text" name="apellido" id="editarApellido" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula *</label>
                        <input type="text" name="cedula" id="editarCedula" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="editarTelefono" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" id="editarCorreo" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-circle me-1"></i>Actualizar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Tickets abiertos de un cliente -->
<div class="modal fade" id="ticketsAbiertosModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-hourglass-split me-2"></i>Tickets abiertos: <span id="ticketsCliente"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Recibo</th><th>Fecha</th><th class="text-end">Total (USD)</th><th></th></tr></thead>
                    <tbody id="ticketsLista"></tbody>
                    <tfoot><tr class="fw-bold"><td colspan="2">Total que debe</td><td class="text-end" id="ticketsTotal"></td><td></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function verTicketsAbiertos(cliente, tickets) {
    document.getElementById('ticketsCliente').textContent = cliente;
    const lista = document.getElementById('ticketsLista');
    lista.innerHTML = '';
    let total = 0;
    tickets.forEach(t => {
        total += t.total;
        const fila = document.createElement('tr');
        fila.innerHTML = '<td></td><td></td><td class="text-end"></td><td class="text-end"><a class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a></td>';
        fila.children[0].textContent = t.recibo;
        fila.children[1].textContent = t.fecha || '';
        fila.children[2].textContent = '$' + t.total.toFixed(2);
        fila.querySelector('a').href = '{{ url('recibos') }}/' + encodeURIComponent(t.id);
        lista.appendChild(fila);
    });
    document.getElementById('ticketsTotal').textContent = '$' + total.toFixed(2);
    new bootstrap.Modal(document.getElementById('ticketsAbiertosModal')).show();
}

function abrirModalEditar(id, nombre, apellido, cedula, telefono, correo) {
    document.getElementById('editarClienteId').value = id;
    document.getElementById('editarNombre').value = nombre;
    document.getElementById('editarApellido').value = apellido;
    document.getElementById('editarCedula').value = cedula;
    document.getElementById('editarTelefono').value = telefono;
    document.getElementById('editarCorreo').value = correo;
    
    const modal = new bootstrap.Modal(document.getElementById('editarClienteModal'));
    modal.show();
}

function eliminarCliente(id, nombre) {
    if (confirm(`¿Está seguro de eliminar el cliente "${nombre}"? Esta acción no se puede deshacer.`)) {
        fetch(`{{ route('clientes.destroy', ':id') }}`.replace(':id', id), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || 
                               document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error al eliminar cliente');
            }
        })
        .catch(error => {
            alert('Error de conexión: ' + error.message);
        });
    }
}

// Manejar envío de formularios con AJAX
document.querySelectorAll('#formCrearCliente, #formEditarCliente').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const modal = bootstrap.Modal.getInstance(this.closest('.modal'));
        
        fetch(this.action, {
            method: this.method || 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || 
                               document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modal.hide();
                location.reload();
            } else {
                alert(data.message || 'Error al procesar la solicitud');
            }
        })
        .catch(error => {
            alert('Error de conexión: ' + error.message);
        });
    });
});
</script>
@endpush
