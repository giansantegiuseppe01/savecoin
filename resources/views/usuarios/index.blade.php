@extends('layouts.sidebar')

@section('title', 'Usuarios - SaveCoin POS')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-3"><i class="bi bi-person-gear me-2"></i>Gestión de Usuarios</h1>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Listado de Usuarios</h5>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#agregarUsuarioModal">
                    <i class="bi bi-plus-circle me-1"></i>Agregar Usuario
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Apellido</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usuarios as $usuario)
                            <tr>
                                <td><strong>{{ $usuario->nombre }}</strong></td>
                                <td><strong>{{ $usuario->apellido }}</strong></td>
                                <td>{{ $usuario->email }}</td>
                                <td>
                                    @if($usuario->rol === 'admin')
                                        <span class="badge bg-primary">Administrador</span>
                                    @else
                                        <span class="badge bg-info">Empleado</span>
                                    @endif
                                </td>
                                <td>{{ $usuario->telefono ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-success">Activo</span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-primary btn-action" 
                                                onclick="abrirModalEditar({{ $usuario->id }})"
                                                title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action" 
                                                onclick="eliminarUsuario({{ $usuario->id }}, @js($usuario->nombre.' '.$usuario->apellido))"
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
            </div>
        </div>
    </div>
</div>

<!-- Modal Agregar Usuario -->
<div class="modal fade" id="agregarUsuarioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Agregar Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUsuario" action="{{ route('usuarios.store') }}" method="POST">
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
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select name="rol" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <option value="admin">Administrador</option>
                            <option value="empleado">Empleado</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control">
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

<!-- Modal Editar Usuario -->
<div class="modal fade" id="editarUsuarioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarUsuario" action="{{ route('usuarios.update', ':id') }}" method="POST">
                @csrf
                @method('PUT')
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
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="editarEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña (dejar en blanco para mantener)</label>
                        <input type="password" name="password" id="editarPassword" class="form-control" minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select name="rol" id="editarRol" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <option value="admin">Administrador</option>
                            <option value="empleado">Empleado</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="editarTelefono" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" id="editarDireccion" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-circle me-1"></i>Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function abrirModalEditar(usuarioId) {
    fetch(`/usuarios/${usuarioId}`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                           document.querySelector('input[name="_token"]').value,
        }
    })
        .then(response => response.json())
        .then(data => {
            const form = document.getElementById('formEditarUsuario');
            form.action = `/usuarios/${usuarioId}`;

            document.getElementById('editarNombre').value = data.nombre;
            document.getElementById('editarApellido').value = data.apellido;
            document.getElementById('editarEmail').value = data.email;
            document.getElementById('editarPassword').value = '';
            document.getElementById('editarRol').value = data.rol;
            document.getElementById('editarTelefono').value = data.telefono || '';
            document.getElementById('editarDireccion').value = data.direccion || '';

            const modal = new bootstrap.Modal(document.getElementById('editarUsuarioModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error al cargar usuario:', error);
            alert('Error al cargar datos del usuario: ' + error.message);
        });
}

function eliminarUsuario(usuarioId, nombre) {
    if (confirm(`¿Está seguro de eliminar el usuario "${nombre}"?`)) {
        fetch(`/usuarios/${usuarioId}`, {
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
                alert(data.message || 'Error al eliminar usuario');
            }
        })
        .catch(error => {
            alert('Error de conexión: ' + error.message);
        });
    }
}

document.querySelectorAll('#formUsuario, #formEditarUsuario').forEach(form => {
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
