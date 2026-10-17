@extends('layouts.sidebar')

@section('title', 'Productos - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-basket me-2"></i>Gestión de Productos</h1>
        
        <!-- KPIs -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-primary">
                    <i class="bi bi-basket kpi-icon"></i>
                    <h6 class="card-title">Total Productos</h6>
                    <div class="kpi-value">{{ $totalProductos }}</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-success">
                    <i class="bi bi-check-circle kpi-icon"></i>
                    <h6 class="card-title">Activos</h6>
                    <div class="kpi-value">{{ $productosActivos }}</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card kpi-card bg-info">
                    <i class="bi bi-layers kpi-icon"></i>
                    <h6 class="card-title">Compuestos</h6>
                    <div class="kpi-value">{{ $productosCompuestos }}</div>
                </div>
            </div>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('productos.index') }}" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="buscar" class="form-control" 
                               placeholder="Nombre o código..." value="{{ request('buscar') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Categoría</label>
                        <select name="categoria" class="form-select">
                            <option value="">Todas</option>
                            @foreach($categorias as $categoria)
                            <option value="{{ $categoria }}" {{ request('categoria') === $categoria ? 'selected' : '' }}>{{ $categoria }}</option>
                            @endforeach
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

        <!-- Tabla de Productos -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Listado de Productos</h5>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#agregarProductoModal">
                    <i class="bi bi-plus-circle me-1"></i>Agregar Producto
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th class="text-end">Precio Venta</th>
                                <th class="text-end">Costo Producción</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productos as $producto)
                            <tr>
                                <td><strong>{{ $producto->codigo }}</strong></td>
                                <td>
                                    <strong>{{ $producto->nombre }}</strong>
                                    @if($producto->descripcion)
                                    <br><small class="text-muted">{{ Str::limit($producto->descripcion, 30) }}</small>
                                    @endif
                                </td>
                                <td>{{ $producto->categoria ?? '-' }}</td>
                                <td class="text-end">${{ number_format($producto->precio_venta, 2) }}</td>
                                <td class="text-end">${{ number_format($producto->costo_produccion, 2) }}</td>
                                <td>
                                    @if($producto->insumos->count() > 0)
                                        <span class="badge bg-info">Compuesto</span>
                                        <br><small class="text-muted">{{ $producto->insumos->count() }} insumos</small>
                                    @else
                                        <span class="badge bg-secondary">Simple</span>
                                    @endif
                                </td>
                                <td>
                                    @if($producto->activo)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-primary btn-action" 
                                                onclick="abrirModalEditar({{ $producto->id }})"
                                                title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action" 
                                                onclick="eliminarProducto({{ $producto->id }})"
                                                title="Eliminar">
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
                        Mostrando {{ $productos->firstItem() }} a {{ $productos->lastItem() }} 
                        de {{ $productos->total() }} registros
                    </div>
                    {{ $productos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Agregar/Editar Producto -->
<div class="modal fade" id="agregarProductoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="productoModalTitle"><i class="bi bi-plus-circle me-2"></i>Agregar Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formProducto" action="{{ route('productos.store') }}" method="POST">
                @csrf
                <input type="hidden" name="producto_id" id="producto_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código *</label>
                            <input type="text" name="codigo" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Precio Venta *</label>
                            <input type="number" name="precio_venta" class="form-control" 
                                   step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Categoría</label>
                            <input type="text" name="categoria" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tiempo Preparación (min)</label>
                            <input type="number" name="tiempo_preparacion_minutos" class="form-control" 
                                   min="0" value="0">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="hidden" name="preparado_en_cocina" value="0">
                                <input class="form-check-input" type="checkbox" name="preparado_en_cocina" id="preparadoEnCocina" value="1">
                                <label class="form-check-label" for="preparadoEnCocina">
                                    Preparado en cocina
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="hidden" name="es_compuesto" value="0">
                                <input class="form-check-input" type="checkbox" name="es_compuesto" id="esCompuesto" value="1" onchange="toggleInsumosSection()">
                                <label class="form-check-label" for="esCompuesto">
                                    <strong>¿Es producto compuesto?</strong>
                                    <br><small class="text-muted">Ej: Hamburguesa, Pizza, etc.</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Insumos para Productos Compuestos -->
                    <div id="insumosSection" class="d-none">
                        <hr>
                        <h6 class="mb-3"><i class="bi bi-layers me-2"></i>Receta - Ingredientes</h6>
                        <div id="insumosContainer">
                            <!-- Los insumos se agregarán dinámicamente aquí -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="agregarFilaInsumo()">
                            <i class="bi bi-plus me-1"></i>Agregar Ingrediente
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let insumosDisponibles = [];
let insumosCargados = false;

// Cargar insumos disponibles
function cargarInsumos() {
    return fetch('/api/productos/insumos')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Insumos cargados:', data);
            insumosDisponibles = data;
            insumosCargados = true;
        })
        .catch(error => {
            console.error('Error al cargar insumos:', error);
            throw error;
        });
}

// Cargar insumos al inicio
cargarInsumos();

function toggleInsumosSection(agregarFila = true) {
    const esCompuesto = document.getElementById('esCompuesto').checked;
    const section = document.getElementById('insumosSection');

    if (esCompuesto) {
        section.classList.remove('d-none');
        if (agregarFila && document.querySelectorAll('.insumo-row').length === 0) {
            agregarFilaInsumo();
        }
    } else {
        section.classList.add('d-none');
        document.getElementById('insumosContainer').innerHTML = '';
    }
}

function agregarFilaInsumo(insumoData = null) {
    const container = document.getElementById('insumosContainer');
    const rowId = Date.now();
    
    const insumosOptions = insumosDisponibles.map(insumo => 
        `<option value="${insumo.id}" data-unidad="${insumo.unidad_medida}" ${insumoData && insumoData.insumo_id === insumo.id ? 'selected' : ''}>
            ${insumo.nombre} (${insumo.codigo}) - $${insumo.costo_unitario}/${insumo.unidad_medida}
        </option>`
    ).join('');
    
    const selectedInsumo = insumoData ? insumosDisponibles.find(i => i.id === insumoData.insumo_id) : null;
    const unidadMedida = selectedInsumo ? selectedInsumo.unidad_medida : (insumoData ? insumoData.unidad_medida : 'g');
    
    const row = document.createElement('div');
    row.className = 'row mb-2 insumo-row align-items-end';
    row.id = `row-${rowId}`;
    row.innerHTML = `
        <div class="col-md-5">
            <label class="form-label small">Insumo</label>
            <select name="insumos[${rowId}][insumo_id]" class="form-select form-select-sm insumo-select" required onchange="updateUnidadMedida(this, '${rowId}')">
                <option value="">Seleccionar...</option>
                ${insumosOptions}
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Cantidad</label>
            <input type="number" name="insumos[${rowId}][cantidad_requerida]" class="form-control form-control-sm" 
                   step="0.0001" min="0.0001" value="${insumoData ? insumoData.cantidad_requerida : ''}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Unidad</label>
            <input type="hidden" name="insumos[${rowId}][unidad_medida]" class="unidad-medida-hidden" value="${unidadMedida}">
            <input type="text" class="form-control form-control-sm unidad-medida-input"
                   value="${unidadMedida}" disabled readonly>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-danger" onclick="eliminarFilaInsumo('${rowId}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(row);
}

function updateUnidadMedida(selectElement, rowId) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const unidadMedida = selectedOption.getAttribute('data-unidad');
    const row = document.getElementById(`row-${rowId}`);
    const unidadInput = row.querySelector('.unidad-medida-input');
    const unidadHidden = row.querySelector('.unidad-medida-hidden');
    if (unidadInput && unidadMedida) {
        unidadInput.value = unidadMedida;
    }
    if (unidadHidden && unidadMedida) {
        unidadHidden.value = unidadMedida;
    }
}

function eliminarFilaInsumo(rowId) {
    const row = document.getElementById(`row-${rowId}`);
    if (row) {
        row.remove();
    }
}

function abrirModalEditar(productoId) {
    // Asegurar que los insumos estén cargados primero
    Promise.all([
        cargarInsumos(),
        fetch(`/productos/${productoId}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                               document.querySelector('input[name="_token"]').value,
            }
        }).then(response => response.json())
    ])
        .then(([, data]) => {
            console.log('Datos del producto:', data); // Debug
            console.log('Insumos disponibles:', insumosDisponibles); // Debug
            const form = document.getElementById('formProducto');
            form.action = `/productos/${productoId}`;
            form.method = 'POST';

            // Agregar método PUT
            let methodInput = form.querySelector('input[name="_method"]');
            if (!methodInput) {
                methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                form.appendChild(methodInput);
            }
            methodInput.value = 'PUT';

            document.getElementById('producto_id').value = productoId;
            document.getElementById('productoModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar Producto';

            // Llenar campos
            form.querySelector('[name="nombre"]').value = data.nombre;
            form.querySelector('[name="codigo"]').value = data.codigo;
            form.querySelector('[name="descripcion"]').value = data.descripcion || '';
            form.querySelector('[name="precio_venta"]').value = data.precio_venta;
            form.querySelector('[name="categoria"]').value = data.categoria || '';
            form.querySelector('[name="tiempo_preparacion_minutos"]').value = data.tiempo_preparacion_minutos || 0;
            form.querySelector('[name="preparado_en_cocina"]').checked = data.preparado_en_cocina;

            const esCompuesto = data.insumos && data.insumos.length > 0;
            document.getElementById('esCompuesto').checked = esCompuesto;
            console.log('Es compuesto:', esCompuesto, 'Insumos:', data.insumos); // Debug

            // Limpiar y cargar insumos si es compuesto
            document.getElementById('insumosContainer').innerHTML = '';
            if (esCompuesto) {
                toggleInsumosSection(false); // No añadir fila automática al editar
                data.insumos.forEach(insumo => {
                    console.log('Procesando insumo:', insumo); // Debug
                    // Extraer datos del pivot
                    const insumoData = {
                        insumo_id: insumo.id,
                        cantidad_requerida: insumo.pivot.cantidad_requerida,
                        unidad_medida: insumo.pivot.unidad_medida
                    };
                    agregarFilaInsumo(insumoData);
                });
            } else {
                toggleInsumosSection();
            }

            const modal = new bootstrap.Modal(document.getElementById('agregarProductoModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error al cargar producto:', error);
            alert('Error al cargar datos del producto: ' + error.message);
        });
}

function eliminarProducto(productoId) {
    if (confirm('¿Está seguro de eliminar este producto?')) {
        fetch(`/productos/${productoId}`, {
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
                alert(data.message || 'Error al eliminar producto');
            }
        })
        .catch(error => {
            alert('Error de conexión: ' + error.message);
        });
    }
}

// Manejar envío del formulario
document.getElementById('formProducto').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const modal = bootstrap.Modal.getInstance(this.closest('.modal'));
    
    fetch(this.action, {
        method: this.method,
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
document.getElementById('agregarProductoModal').addEventListener('hidden.bs.modal', function() {
    const form = document.getElementById('formProducto');
    form.reset();
    form.action = '{{ route('productos.store') }}';
    form.method = 'POST';
    
    let methodInput = form.querySelector('input[name="_method"]');
    if (methodInput) {
        methodInput.remove();
    }
    
    document.getElementById('producto_id').value = '';
    document.getElementById('productoModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Agregar Producto';
    document.getElementById('insumosContainer').innerHTML = '';
    document.getElementById('insumosSection').classList.add('d-none');
});
</script>
@endpush
