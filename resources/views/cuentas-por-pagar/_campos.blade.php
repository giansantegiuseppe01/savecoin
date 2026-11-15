@php $p = $prefijo; @endphp
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Acreedor / proveedor *</label>
        <input type="text" name="acreedor" class="form-control" maxlength="150" required placeholder="Ej: Distribuidora Pérez">
    </div>
    <div class="col-md-4">
        <label class="form-label">Tipo *</label>
        <select name="tipo" class="form-select" required>
            @foreach(\App\Models\CuentaPorPagar::TIPOS as $valor => $nombre)
                <option value="{{ $valor }}">{{ $nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label">Concepto</label>
        <input type="text" name="concepto" class="form-control" maxlength="255" placeholder="Ej: Compra de harina">
    </div>
    <div class="col-md-4">
        <label class="form-label">N.º de factura / documento</label>
        <input type="text" name="numero_documento" class="form-control" maxlength="100">
    </div>
    <div class="col-md-4">
        <label class="form-label">Monto (USD) *</label>
        <input type="number" name="monto" class="form-control" step="0.01" min="0.01" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Fecha de emisión *</label>
        <input type="date" name="fecha_emision" class="form-control" value="{{ now()->toDateString() }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Fecha de vencimiento</label>
        <input type="date" name="fecha_vencimiento" class="form-control">
    </div>
    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2"></textarea>
    </div>
</div>
