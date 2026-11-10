@extends('layouts.sidebar')

@section('title', 'Configuración - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-gear me-2"></i>Configuración del Sistema</h1>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-building me-2"></i>Información de la Empresa</h5>
            </div>
            <div class="card-body">
                <form id="formConfiguracion" action="{{ route('configuracion.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre de la Empresa *</label>
                            <input type="text" name="nombre_empresa" class="form-control" 
                                   value="{{ $config->nombre_empresa }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">RIF</label>
                            <input type="text" name="rif" class="form-control" 
                                   value="{{ $config->rif }}" placeholder="Ej: J-12345678-9">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Logo (PNG)</label>
                            <input type="file" name="logo" class="form-control" 
                                   accept="image/png">
                            <small class="text-muted">Formato PNG, máximo 2MB</small>
                            @if($config->logo)
                                <br><small class="text-info">Logo actual: {{ basename($config->logo) }}</small>
                            @endif
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Eslogan</label>
                            <input type="text" name="eslogan" class="form-control" 
                                   value="{{ $config->eslogan }}" placeholder="Ej: El mejor sabor de la ciudad">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tasa de Cambio (USD a BS) *</label>
                            <input type="number" name="tasa_cambio" class="form-control" 
                                   step="0.0001" min="0.0001" value="{{ number_format($config->tasa_cambio, 4) }}" required>
                            <small class="text-muted">1 USD = {{ number_format($config->tasa_cambio, 4) }} BS</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" 
                                   value="{{ $config->telefono }}" placeholder="Ej: +58 412-1234567">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ $config->email }}" placeholder="contacto@empresa.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <textarea name="direccion" class="form-control" rows="2">{{ $config->direccion }}</textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Información importante:</strong>
                        <ul class="mb-0 mt-2">
                            <li>La <strong>Tasa de Cambio</strong> se usará para convertir precios de USD a BS en ventas y recibos</li>
                            <li>El <strong>Inventario y Productos</strong> se mantienen en USD</li>
                            <li>El <strong>Libro Contable</strong> y las <strong>Ventas</strong> se registran en BS</li>
                            <li>Al imprimir un recibo, el precio se mostrará en BS según la tasa configurada</li>
                        </ul>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>Guardar Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Formas de pago -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-credit-card me-2"></i>Formas de Pago</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="abrirFormaPago()">
                    <i class="bi bi-plus-circle me-1"></i>Añadir
                </button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Los datos de pago activos (Pago Móvil, transferencia, Zelle...) se imprimen al final de la pre-cuenta
                    para que el cliente los tenga a la mano.
                </p>
                @forelse($formasPago as $fp)
                    <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-start gap-2 {{ $fp->activo ? '' : 'opacity-50' }}">
                        <div>
                            <strong>{{ $fp->nombre }}</strong>
                            @unless($fp->activo)<span class="badge bg-secondary ms-1">Inactiva</span>@endunless
                            <div class="small text-muted" style="white-space: pre-line;">{{ $fp->datos }}</div>
                        </div>
                        <div class="text-nowrap">
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-fp="{{ json_encode(['id' => $fp->id, 'nombre' => $fp->nombre, 'datos' => $fp->datos, 'activo' => $fp->activo]) }}"
                                    onclick="abrirFormaPago(JSON.parse(this.dataset.fp))">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarFormaPago({{ $fp->id }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Aún no has añadido formas de pago.</p>
                @endforelse
            </div>
        </div>

        <div class="modal fade" id="modalFormaPago" tabindex="-1">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Forma de pago</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="fpId">
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" id="fpNombre" class="form-control" maxlength="100" placeholder="Ej: Pago Móvil">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Datos *</label>
                            <textarea id="fpDatos" class="form-control" rows="5" maxlength="1000"
                                      placeholder="Banco: Venezuela (0102)&#10;Teléfono: 0412-1234567&#10;Cédula: V-12345678"></textarea>
                            <small class="text-muted">Cada línea se imprime tal cual en el ticket (máx. ~32 caracteres por línea).</small>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fpActivo" checked>
                            <label class="form-check-label" for="fpActivo">Activa (se imprime en la pre-cuenta)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" onclick="guardarFormaPago()">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-eye me-2"></i>Vista Previa</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        @if($config->logo)
                            <img src="{{ asset($config->logo) }}" alt="Logo" class="img-fluid rounded" style="max-height: 80px;">
                        @else
                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 80px;">
                                <i class="bi bi-building text-muted" style="font-size: 2rem;"></i>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-10">
                        <h4 class="mb-1">{{ $config->nombre_empresa }}</h4>
                        @if($config->eslogan)
                            <p class="text-muted mb-2">{{ $config->eslogan }}</p>
                        @endif
                        @if($config->rif)
                            <p class="mb-1"><strong>RIF:</strong> {{ $config->rif }}</p>
                        @endif
                        @if($config->direccion)
                            <p class="mb-1"><strong>Dirección:</strong> {{ $config->direccion }}</p>
                        @endif
                        @if($config->telefono)
                            <p class="mb-1"><strong>Teléfono:</strong> {{ $config->telefono }}</p>
                        @endif
                        @if($config->email)
                            <p class="mb-0"><strong>Email:</strong> {{ $config->email }}</p>
                        @endif
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-currency-dollar me-2"></i>
                            <strong>Tasa de Cambio Actual:</strong> 1 USD = {{ number_format($config->tasa_cambio, 4) }} BS
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-calculator me-2"></i>
                            <strong>Ejemplo:</strong> $10.00 USD = {{ number_format(10 * $config->tasa_cambio, 2) }} BS
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const FP_URL = @json(route('configuracion.formas-pago.store'));
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]').value;

function abrirFormaPago(fp) {
    document.getElementById('fpId').value = fp ? fp.id : '';
    document.getElementById('fpNombre').value = fp ? fp.nombre : '';
    document.getElementById('fpDatos').value = fp ? fp.datos : '';
    document.getElementById('fpActivo').checked = fp ? !!fp.activo : true;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFormaPago')).show();
}

function enviarFormaPago(url, method, body) {
    return fetch(url, {
        method,
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json'},
        body: body ? JSON.stringify(body) : undefined,
    }).then(r => r.json().then(d => ({ok: r.ok, d})));
}

function guardarFormaPago() {
    const id = document.getElementById('fpId').value;
    const body = {
        nombre: document.getElementById('fpNombre').value.trim(),
        datos: document.getElementById('fpDatos').value.trim(),
        activo: document.getElementById('fpActivo').checked,
    };
    if (!body.nombre || !body.datos) { alert('Nombre y datos son obligatorios.'); return; }
    enviarFormaPago(id ? FP_URL + '/' + id : FP_URL, id ? 'PUT' : 'POST', body)
        .then(({ok, d}) => ok ? location.reload() : alert(d.message || 'No se pudo guardar.'))
        .catch(e => alert('Error de conexión: ' + e.message));
}

function eliminarFormaPago(id) {
    if (!confirm('¿Eliminar esta forma de pago?')) return;
    enviarFormaPago(FP_URL + '/' + id, 'DELETE')
        .then(({ok, d}) => ok ? location.reload() : alert(d.message || 'No se pudo eliminar.'));
}

document.getElementById('formConfiguracion').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
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
            alert('Configuración actualizada exitosamente.');
            location.reload();
        } else {
            alert(data.message || 'Error al procesar la solicitud');
        }
    })
    .catch(error => {
        alert('Error de conexión: ' + error.message);
    });
});
</script>
@endpush
