@extends('layouts.sidebar')

@section('title', 'Libro Contable - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-book me-2"></i>Libro Contable</h1>
        
        <!-- Saldo Actual -->
        <div class="card mb-4 bg-gradient-primary">
            <div class="card-body text-white">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-1">Saldo Actual del Local (BS)</h5>
                        <h2 class="display-4 fw-bold">Bs {{ number_format($saldoActual, 2) }}</h2>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <button class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#gestionCategoriasModal">
                                <i class="bi bi-tags me-2"></i>Categorías
                            </button>
                            <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#agregarMovimientoModal">
                                <i class="bi bi-plus-circle me-2"></i>Agregar Movimiento
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-success">
                    <i class="bi bi-arrow-down-circle kpi-icon"></i>
                    <h6 class="card-title">Total Entradas</h6>
                    <div class="kpi-value">Bs {{ number_format($totalEntradas, 2) }}</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-danger">
                    <i class="bi bi-arrow-up-circle kpi-icon"></i>
                    <h6 class="card-title">Total Salidas</h6>
                    <div class="kpi-value">Bs {{ number_format($totalSalidas, 2) }}</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-info">
                    <i class="bi bi-list-check kpi-icon"></i>
                    <h6 class="card-title">Total Movimientos</h6>
                    <div class="kpi-value">{{ $totalMovimientos }}</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('libro-contable.index') }}" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" 
                               value="{{ request('fecha_inicio') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" 
                               value="{{ request('fecha_fin') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo Movimiento</label>
                        <select name="tipo_movimiento" class="form-select">
                            <option value="">Todos</option>
                            <option value="entrada" {{ request('tipo_movimiento') === 'entrada' ? 'selected' : '' }}>Entrada</option>
                            <option value="salida" {{ request('tipo_movimiento') === 'salida' ? 'selected' : '' }}>Salida</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Movimientos -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Historial de Movimientos</h5>
                <a href="{{ route('libro-contable.pdf', request()->query()) }}" class="btn btn-success" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Descargar PDF
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Categoría</th>
                                <th>Descripción</th>
                                <th>Referencia</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end">Saldo Después</th>
                                <th>Usuario</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movimientos as $movimiento)
                            <tr>
                                <td>{{ $movimiento->fecha->format('d/m/Y') }}</td>
                                <td>
                                    @if($movimiento->tipo_movimiento === 'entrada')
                                        <span class="badge bg-success">Entrada</span>
                                    @else
                                        <span class="badge bg-danger">Salida</span>
                                    @endif
                                </td>
                                <td>
                                    @if($movimiento->categoria)
                                        <span class="badge" style="background-color: {{ $movimiento->categoria->color }}; color: white;">
                                            <i class="bi {{ $movimiento->categoria->icono }} me-1"></i>{{ $movimiento->categoria->nombre }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">Sin categoría</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $movimiento->descripcion }}</strong>
                                </td>
                                <td>{{ $movimiento->referencia ?? '-' }}</td>
                                <td class="text-end">
                                    <span class="{{ $movimiento->tipo_movimiento === 'entrada' ? 'text-success' : 'text-danger' }} fw-bold">
                                        {{ $movimiento->tipo_movimiento === 'entrada' ? '+' : '-' }}Bs {{ number_format($movimiento->monto, 2) }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold">Bs {{ number_format($movimiento->saldo_despues, 2) }}</td>
                                <td>
                                    <small>{{ $movimiento->user->name ?? 'Sistema' }}</small>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-danger btn-action"
                                            onclick="eliminarMovimiento({{ $movimiento->id }})"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
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

<!-- Modal Agregar Movimiento -->
<div class="modal fade" id="agregarMovimientoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Agregar Movimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formMovimiento" action="{{ route('libro-contable.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" class="form-control" 
                               value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Movimiento *</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="tipo_movimiento" id="tipoEntrada" 
                                   value="entrada" checked onchange="toggleTipoMovimiento()">
                            <label class="btn btn-outline-success" for="tipoEntrada">
                                <i class="bi bi-arrow-down-circle me-1"></i>Entrada
                            </label>
                            
                            <input type="radio" class="btn-check" name="tipo_movimiento" id="tipoSalida" 
                                   value="salida" onchange="toggleTipoMovimiento()">
                            <label class="btn btn-outline-danger" for="tipoSalida">
                                <i class="bi bi-arrow-up-circle me-1"></i>Salida
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Monto *</label>
                        <input type="number" name="monto" class="form-control" 
                               step="0.01" min="0.01" required>
                        <small class="text-muted" id="montoHelp">Ej: Cierre de caja, inyección de capital</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción *</label>
                        <textarea name="descripcion" class="form-control" rows="3" 
                                  placeholder="Describa el movimiento..." required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Categoría (opcional)</label>
                        <select name="categoria_id" class="form-select" id="categoriaSelect">
                            <option value="">Sin categoría</option>
                            @foreach($categorias ?? [] as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Referencia (opcional)</label>
                        <input type="text" name="referencia" class="form-control"
                               placeholder="Ej: #REC-001, Factura #123">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Gestión de Categorías -->
<div class="modal fade" id="gestionCategoriasModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-tags me-2"></i>Gestión de Categorías</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Formulario para añadir categoría -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nueva Categoría</h6>
                    </div>
                    <div class="card-body">
                        <form id="formCategoria" class="row g-3">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="nombre" class="form-control" required placeholder="Ej: Compra Insumos">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Color</label>
                                <input type="color" name="color" class="form-control form-control-color" value="#6c757d">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Icono</label>
                                <select name="icono" class="form-select">
                                    <option value="bi-tag">🏷️ Etiqueta</option>
                                    <option value="bi-cash">💰 Dinero</option>
                                    <option value="bi-cart">🛒 Carrito</option>
                                    <option value="bi-building">🏢 Edificio</option>
                                    <option value="bi-tools">🔧 Herramientas</option>
                                    <option value="bi-people">👥 Personas</option>
                                    <option value="bi-lightning">⚡ Rayo</option>
                                    <option value="bi-star">⭐ Estrella</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <input type="text" name="descripcion" class="form-control" placeholder="Descripción opcional">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-plus-circle me-2"></i>Añadir Categoría
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de categorías existentes -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-list me-2"></i>Categorías Existentes</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Color</th>
                                        <th>Icono</th>
                                        <th>Descripción</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="categoriasTableBody">
                                    <!-- Se llenará dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
@endpush

@push('scripts')
<script>

// Escapa texto antes de insertarlo en HTML (previene XSS almacenado)
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
}
// Cargar categorías al abrir el modal
document.getElementById('gestionCategoriasModal').addEventListener('show.bs.modal', function() {
    cargarCategorias();
});

function cargarCategorias() {
    fetch('/categorias-libro-contable')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('categoriasTableBody');
            tbody.innerHTML = '';

            data.forEach(categoria => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${escapeHtml(categoria.nombre)}</strong></td>
                    <td>
                        <span class="badge" style="background-color: ${escapeHtml(categoria.color)}; color: white;">
                            ${escapeHtml(categoria.color)}
                        </span>
                    </td>
                    <td><i class="bi ${escapeHtml(categoria.icono)}"></i></td>
                    <td>${escapeHtml(categoria.descripcion || '-')}</td>
                    <td>
                        ${categoria.activo
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>'}
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-categoria">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                // El nombre se pasa por closure (no por HTML) para evitar inyección en onclick
                row.querySelector('.btn-eliminar-categoria').addEventListener('click', () => {
                    eliminarCategoria(categoria.id, categoria.nombre);
                });
                tbody.appendChild(row);
            });

            // Actualizar también el select del formulario de movimientos
            actualizarSelectCategorias(data);
        })
        .catch(error => {
            console.error('Error al cargar categorías:', error);
        });
}

function actualizarSelectCategorias(categorias) {
    const select = document.getElementById('categoriaSelect');
    const valorActual = select.value;

    select.innerHTML = '<option value="">Sin categoría</option>';
    categorias.forEach(categoria => {
        if (categoria.activo) {
            select.innerHTML += `<option value="${escapeHtml(categoria.id)}">${escapeHtml(categoria.nombre)}</option>`;
        }
    });

    select.value = valorActual;
}

function eliminarCategoria(id, nombre) {
    if (confirm(`¿Está seguro de eliminar la categoría "${nombre}"?`)) {
        fetch(`/categorias-libro-contable/${id}`, {
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
                cargarCategorias();
                alert('Categoría eliminada exitosamente');
            } else {
                alert(data.message || 'Error al eliminar categoría');
            }
        })
        .catch(error => {
            alert('Error de conexión: ' + error.message);
        });
    }
}

// Manejar envío del formulario de categorías
document.getElementById('formCategoria').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('/categorias-libro-contable', {
        method: 'POST',
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
            this.reset();
            cargarCategorias();
            alert('Categoría creada exitosamente');
        } else {
            alert(data.message || 'Error al crear categoría');
        }
    })
    .catch(error => {
        alert('Error de conexión: ' + error.message);
    });
});

function toggleTipoMovimiento() {
    const tipoSalida = document.getElementById('tipoSalida').checked;
    const montoHelp = document.getElementById('montoHelp');

    if (tipoSalida) {
        montoHelp.textContent = 'Ej: Compras, gastos, pagos';
    } else {
        montoHelp.textContent = 'Ej: Cierre de caja, inyección de capital';
    }
}

function eliminarMovimiento(id) {
    if (confirm('¿Está seguro de eliminar este movimiento? Esto recalculará todos los saldos posteriores.')) {
        fetch(`/libro-contable/${id}`, {
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
                alert(data.message || 'Error al eliminar movimiento');
            }
        })
        .catch(error => {
            alert('Error de conexión: ' + error.message);
        });
    }
}

// Manejar envío del formulario
document.getElementById('formMovimiento').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const modal = bootstrap.Modal.getInstance(this.closest('.modal'));

    fetch(this.action, {
        method: 'POST',
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

// Resetear modal al cerrar
document.getElementById('agregarMovimientoModal').addEventListener('hidden.bs.modal', function() {
    const form = document.getElementById('formMovimiento');
    form.reset();
    document.querySelector('input[name="fecha"]').value = '{{ now()->format('Y-m-d') }}';
    document.getElementById('tipoEntrada').checked = true;
    toggleTipoMovimiento();
});
</script>
@endpush
