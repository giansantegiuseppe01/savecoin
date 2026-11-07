@extends('layouts.sidebar')

@section('title', 'Inventario - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-box-seam me-2"></i>Gestión de Inventario</h1>
        
        <!-- KPIs -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-primary">
                    <i class="bi bi-box-seam kpi-icon"></i>
                    <h6 class="card-title">Total Insumos</h6>
                    <div class="kpi-value">{{ $totalInsumos }}</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-success">
                    <i class="bi bi-check-circle kpi-icon"></i>
                    <h6 class="card-title">Activos</h6>
                    <div class="kpi-value">{{ $insumosActivos }}</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-danger">
                    <i class="bi bi-exclamation-triangle kpi-icon"></i>
                    <h6 class="card-title">Stock Bajo</h6>
                    <div class="kpi-value">{{ $stockBajo }}</div>
                </div>
            </div>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('inventario.index') }}" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="buscar" class="form-control" 
                               placeholder="Nombre o código..." value="{{ request('buscar') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="activo" {{ request('estado') === 'activo' ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ request('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
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

        <!-- Tabla de Inventario -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Listado de Insumos</h5>
                <div>
                    <a href="{{ route('inventario.pdf', request()->query()) }}" class="btn btn-success me-2" target="_blank">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#crearInsumoModal">
                        <i class="bi bi-plus-circle me-1"></i>Nuevo Insumo
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#movimientoStockModal">
                        <i class="bi bi-arrow-left-right me-1"></i>Nuevo Movimiento
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Unidad</th>
                                <th class="text-end">Stock Actual</th>
                                <th class="text-end">Stock Mínimo</th>
                                <th class="text-end">Costo Unitario</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($insumos as $insumo)
                            <tr class="{{ $insumo->stock_actual < $insumo->stock_minimo ? 'table-warning' : '' }}">
                                <td><strong>{{ $insumo->codigo }}</strong></td>
                                <td>
                                    <strong>{{ $insumo->nombre }}</strong>
                                    @if($insumo->esPreparado())
                                    <span class="badge bg-info text-dark" title="Se prepara con otros insumos">Preparado</span>
                                    @endif
                                    @if($insumo->descripcion)
                                    <br><small class="text-muted">{{ Str::limit($insumo->descripcion, 30) }}</small>
                                    @endif
                                </td>
                                <td>{{ $insumo->unidad_medida }}</td>
                                <td class="text-end">
                                    <span class="badge {{ $insumo->stock_actual < $insumo->stock_minimo ? 'bg-danger' : 'bg-success' }} fs-6">
                                        {{ number_format($insumo->stock_actual, 0) }}
                                    </span>
                                </td>
                                <td class="text-end">{{ number_format($insumo->stock_minimo, 0) }}</td>
                                <td class="text-end">${{ number_format($insumo->costo_unitario, 4) }}</td>
                                <td>
                                    @if($insumo->activo)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-warning btn-action" 
                                                onclick="abrirModalEditar({{ $insumo->id }}, @js($insumo->codigo), @js($insumo->nombre), @js($insumo->descripcion ?? ''), @js($insumo->unidad_medida), {{ (float) $insumo->costo_unitario }}, {{ (float) $insumo->stock_actual }}, {{ (float) $insumo->stock_minimo }}, {{ $insumo->activo ? 'true' : 'false' }}, @js($insumo->rendimiento !== null ? (float) $insumo->rendimiento : null), @js($insumo->ingredientes->map(fn ($i) => ['insumo_id' => $i->id, 'cantidad' => (float) $i->pivot->cantidad])->values()))"
                                                title="Editar Insumo">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action" 
                                                onclick="eliminarInsumo({{ $insumo->id }}, @js($insumo->nombre))"
                                                title="Eliminar Insumo">
                                            <i class="bi bi-trash"></i>
                                        </button>
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
                        Mostrando {{ $insumos->firstItem() }} a {{ $insumos->lastItem() }} 
                        de {{ $insumos->total() }} registros
                    </div>
                    {{ $insumos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Insumo -->
<div class="modal fade" id="crearInsumoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Insumo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCrearInsumo" action="{{ route('inventario.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código *</label>
                        <input type="text" name="codigo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unidad de Medida *</label>
                            <select name="unidad_medida" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="G">G (Gramos)</option>
                                <option value="Kg">Kg (Kilogramos)</option>
                                <option value="Lt">Lt (Litros)</option>
                                <option value="Unidad">Unidad</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Costo Unitario (USD) *</label>
                            <input type="number" name="costo_unitario" class="form-control" step="0.0001" min="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock Inicial *</label>
                            <input type="number" name="stock_actual" class="form-control" step="0.0001" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock Mínimo *</label>
                            <input type="number" name="stock_minimo" class="form-control" step="0.0001" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" name="activo" class="form-check-input" id="activoCheck" value="1" checked>
                            <label class="form-check-label" for="activoCheck">Activo</label>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="crearEsPreparado" onchange="alternarReceta('crear')">
                            <label class="form-check-label" for="crearEsPreparado"><strong>Es un insumo preparado</strong> (se hace con otros insumos)</label>
                        </div>
                        <small class="text-muted">Al cargar stock de este insumo se descontarán sus ingredientes en proporción.</small>
                    </div>
                    <div id="crearReceta" style="display:none">
                        <div class="mb-2">
                            <label class="form-label">Un lote rinde</label>
                            <input type="number" name="rendimiento" id="crearRendimiento" class="form-control" step="0.0001" min="0" placeholder="Por defecto, la suma de los ingredientes">
                        </div>
                        <label class="form-label">Ingredientes de un lote</label>
                        <div id="crearFilas"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="agregarIngrediente('crear')">
                            <i class="bi bi-plus"></i> Agregar ingrediente
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Crear Insumo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Insumo -->
<div class="modal fade" id="editarInsumoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Insumo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarInsumo" action="{{ route('inventario.update') }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="id" id="editarInsumoId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código *</label>
                        <input type="text" name="codigo" id="editarCodigo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" id="editarNombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="editarDescripcion" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unidad de Medida *</label>
                            <select name="unidad_medida" id="editarUnidadMedida" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="G">G (Gramos)</option>
                                <option value="Kg">Kg (Kilogramos)</option>
                                <option value="Lt">Lt (Litros)</option>
                                <option value="Unidad">Unidad</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Costo Unitario (USD) *</label>
                            <input type="number" name="costo_unitario" id="editarCostoUnitario" class="form-control" step="0.0001" min="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock Actual *</label>
                            <input type="number" name="stock_actual" id="editarStockActual" class="form-control" step="0.0001" min="0" disabled readonly>
                            <small class="text-muted">Use el módulo de movimientos para ajustar stock</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock Mínimo *</label>
                            <input type="number" name="stock_minimo" id="editarStockMinimo" class="form-control" step="0.0001" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" name="activo" class="form-check-input" id="editarActivoCheck" value="1">
                            <label class="form-check-label" for="editarActivoCheck">Activo</label>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editarEsPreparado" onchange="alternarReceta('editar')">
                            <label class="form-check-label" for="editarEsPreparado"><strong>Es un insumo preparado</strong> (se hace con otros insumos)</label>
                        </div>
                        <small class="text-muted">Al cargar stock de este insumo se descontarán sus ingredientes en proporción.</small>
                    </div>
                    <div id="editarReceta" style="display:none">
                        <div class="mb-2">
                            <label class="form-label">Un lote rinde</label>
                            <input type="number" name="rendimiento" id="editarRendimiento" class="form-control" step="0.0001" min="0" placeholder="Por defecto, la suma de los ingredientes">
                        </div>
                        <label class="form-label">Ingredientes de un lote</label>
                        <div id="editarFilas"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="agregarIngrediente('editar')">
                            <i class="bi bi-plus"></i> Agregar ingrediente
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-circle me-1"></i>Actualizar Insumo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Movimiento de Stock -->
<div class="modal fade" id="movimientoStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Movimiento de Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formMovimientoStock" action="{{ route('inventario.agregar-stock') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipo de Movimiento *</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="tipo_movimiento" id="tipoEntrada" value="entrada" checked>
                            <label class="btn btn-outline-success" for="tipoEntrada">
                                <i class="bi bi-plus-circle me-1"></i>Agregar Stock
                            </label>
                            <input type="radio" class="btn-check" name="tipo_movimiento" id="tipoSalida" value="salida">
                            <label class="btn btn-outline-warning" for="tipoSalida">
                                <i class="bi bi-dash-circle me-1"></i>Descontar Stock
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Insumo *</label>
                        <select name="insumo_id" class="form-select" required>
                            <option value="">Seleccionar insumo...</option>
                            @foreach($insumos as $insumo)
                            <option value="{{ $insumo->id }}">{{ $insumo->nombre }} (Stock: {{ number_format($insumo->stock_actual, 0) }} {{ $insumo->unidad_medida }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" name="cantidad" class="form-control" 
                               step="0.0001" min="0.0001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo *</label>
                        <textarea name="motivo" class="form-control" rows="3" 
                                  placeholder="Describa el motivo de este movimiento..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Procesar Movimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const INSUMOS_DISPONIBLES = @json($todosInsumos);
let ingredienteFilaId = 0;

function alternarReceta(prefijo) {
    const activo = document.getElementById(prefijo + 'EsPreparado').checked;
    document.getElementById(prefijo + 'Receta').style.display = activo ? 'block' : 'none';
    // Sin el interruptor, no se envía receta (y se borra la que hubiera)
    document.querySelectorAll('#' + prefijo + 'Receta input, #' + prefijo + 'Receta select').forEach(el => el.disabled = !activo);
    if (activo && document.getElementById(prefijo + 'Filas').children.length === 0) agregarIngrediente(prefijo);
}

function agregarIngrediente(prefijo, insumoId = '', cantidad = '') {
    const n = ++ingredienteFilaId; // contador: Date.now() repetía nombres de campo al crear filas seguidas
    const propio = prefijo === 'editar' ? document.getElementById('editarInsumoId').value : '';
    const opciones = INSUMOS_DISPONIBLES
        .filter(i => String(i.id) !== String(propio))
        .map(i => `<option value="${i.id}" ${String(i.id) === String(insumoId) ? 'selected' : ''}>${i.nombre.replace(/</g, '&lt;')} (${i.unidad_medida})</option>`)
        .join('');
    const fila = document.createElement('div');
    fila.className = 'input-group mb-2';
    fila.innerHTML = `
        <select name="ingredientes[${n}][insumo_id]" class="form-select" required>
            <option value="">Insumo...</option>${opciones}
        </select>
        <input type="number" name="ingredientes[${n}][cantidad]" class="form-control" step="0.0001" min="0.0001" placeholder="Cantidad" value="${cantidad}" required style="max-width:130px">
        <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>`;
    document.getElementById(prefijo + 'Filas').appendChild(fila);
}

function abrirModalEditar(id, codigo, nombre, descripcion, unidadMedida, costoUnitario, stockActual, stockMinimo, activo, rendimiento = null, ingredientes = []) {
    document.getElementById('editarInsumoId').value = id;
    document.getElementById('editarCodigo').value = codigo;
    document.getElementById('editarNombre').value = nombre;
    document.getElementById('editarDescripcion').value = descripcion;
    document.getElementById('editarUnidadMedida').value = unidadMedida;
    document.getElementById('editarCostoUnitario').value = costoUnitario;
    document.getElementById('editarStockActual').value = stockActual;
    document.getElementById('editarStockMinimo').value = stockMinimo;
    document.getElementById('editarActivoCheck').checked = activo;

    // Receta del insumo preparado
    document.getElementById('editarFilas').innerHTML = '';
    document.getElementById('editarEsPreparado').checked = ingredientes.length > 0;
    document.getElementById('editarRendimiento').value = rendimiento ?? '';
    ingredientes.forEach(f => agregarIngrediente('editar', f.insumo_id, f.cantidad));
    alternarReceta('editar');
    
    const modal = new bootstrap.Modal(document.getElementById('editarInsumoModal'));
    modal.show();
}

function eliminarInsumo(insumoId, nombre) {
    if (confirm(`¿Está seguro de eliminar el insumo "${nombre}"? Esta acción no se puede deshacer.`)) {
        fetch(`{{ route('inventario.destroy', ':id') }}`.replace(':id', insumoId), {
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
                alert(data.message || 'Error al eliminar insumo');
            }
        })
        .catch(error => {
            alert('Error de conexión: ' + error.message);
        });
    }
}

// Los campos de receta empiezan desactivados hasta marcar "Es un insumo preparado"
alternarReceta('crear');

// Manejar envío de formularios con AJAX
document.querySelectorAll('#formCrearInsumo, #formEditarInsumo, #formMovimientoStock').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Remove stock_actual from edit form (should only be updated via movements)
        if (this.id === 'formEditarInsumo') {
            formData.delete('stock_actual');
        }
        
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
