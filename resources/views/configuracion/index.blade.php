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
