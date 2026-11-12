<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AjusteSistema extends Model
{
    protected $table = 'ajustes_sistema';

    protected $fillable = ['clave', 'valor'];

    public static function obtener(string $clave, ?string $default = null): ?string
    {
        if (! Schema::hasTable('ajustes_sistema')) {
            return $default;
        }

        return self::where('clave', $clave)->value('valor') ?? $default;
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        self::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
