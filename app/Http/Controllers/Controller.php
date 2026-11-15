<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Ordena una consulta con el orden elegido en la cabecera de la tabla (?orden=clave&dir=asc|desc).
     * Solo acepta las claves de $columnas, así que nunca se ordena por una columna que no esté permitida.
     * Si no hay orden elegido, aplica el orden por defecto.
     *
     * @param  array<string, string|\Illuminate\Contracts\Database\Query\Expression>  $columnas
     * @param  array<int, array{0: string|\Illuminate\Contracts\Database\Query\Expression, 1: string}>  $porDefecto
     */
    protected function ordenar(Builder $query, Request $request, array $columnas, array $porDefecto): Builder
    {
        $clave = (string) $request->input('orden', '');

        if ($clave !== '' && array_key_exists($clave, $columnas)) {
            $dir = strtolower((string) $request->input('dir')) === 'asc' ? 'asc' : 'desc';

            return $query->reorder()
                ->orderBy($columnas[$clave], $dir)
                ->orderBy($query->getModel()->getQualifiedKeyName(), 'asc');
        }

        foreach ($porDefecto as [$columna, $dir]) {
            $query->orderBy($columna, $dir);
        }

        return $query;
    }
}
