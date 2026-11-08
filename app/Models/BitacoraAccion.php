<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraAccion extends Model
{
    protected $table = 'bitacora_acciones';

    protected $fillable = ['user_id', 'accion', 'entidad', 'entidad_id', 'descripcion', 'motivo', 'datos'];

    protected $casts = ['datos' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function registrar(string $accion, string $descripcion, ?string $entidad = null, $entidadId = null, ?string $motivo = null, ?array $datos = null, ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId !== null ? (string) $entidadId : null,
            'descripcion' => $descripcion,
            'motivo' => $motivo,
            'datos' => $datos,
        ]);
    }
}
