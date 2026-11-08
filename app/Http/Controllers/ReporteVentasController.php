<?php

namespace App\Http\Controllers;

use App\Models\DetalleOrden;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de ventas por producto: qué se vendió, cuántas unidades y cuánto dinero, por día o por
 * rango de fechas, con ranking del producto más vendido.
 */
class ReporteVentasController extends Controller
{
    public function productos(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'producto_id' => 'nullable|integer',
            'categoria' => 'nullable|string|max:100',
            'agrupar' => 'nullable|in:total,dia',
        ]);

        $inicio = $request->filled('fecha_inicio') ? Carbon::parse($request->input('fecha_inicio'))->startOfDay() : now()->startOfDay();
        $fin = $request->filled('fecha_fin') ? Carbon::parse($request->input('fecha_fin'))->endOfDay() : $inicio->copy()->endOfDay();
        if ($fin->lt($inicio)) {
            [$inicio, $fin] = [$fin->copy()->startOfDay(), $inicio->copy()->endOfDay()];
        }
        $agrupar = $request->input('agrupar', 'total');

        $query = DetalleOrden::query()
            ->join('ordens', 'ordens.id', '=', 'detalle_ordens.orden_id')
            ->leftJoin('productos', 'productos.id', '=', 'detalle_ordens.producto_id')
            ->whereNull('ordens.deleted_at')
            ->where('ordens.tipo', 'venta')
            ->whereIn('ordens.estado', ['cerrada', 'completada'])
            ->whereBetween('ordens.fecha_hora', [$inicio, $fin]);

        if ($request->filled('producto_id')) {
            $query->where('detalle_ordens.producto_id', $request->integer('producto_id'));
        }
        if ($request->filled('categoria')) {
            $query->where('productos.categoria', $request->input('categoria'));
        }

        $columnas = [
            'detalle_ordens.producto_id',
            DB::raw('COALESCE(productos.nombre, MAX(detalle_ordens.producto_nombre)) as producto'),
            DB::raw("COALESCE(productos.categoria, '') as categoria"),
            DB::raw('SUM(detalle_ordens.cantidad) as unidades'),
            DB::raw('SUM(COALESCE(detalle_ordens.total, detalle_ordens.subtotal)) as ingresos'),
            DB::raw('COUNT(DISTINCT detalle_ordens.orden_id) as recibos'),
        ];
        $grupos = ['detalle_ordens.producto_id', 'productos.nombre', 'productos.categoria'];

        if ($agrupar === 'dia') {
            $columnas[] = DB::raw('DATE(ordens.fecha_hora) as dia');
            $grupos[] = DB::raw('DATE(ordens.fecha_hora)');
        }

        $filas = $query->select($columnas)->groupBy($grupos)->get();

        $filas = $filas->map(function ($f) {
            $f->unidades = (float) $f->unidades;
            $f->ingresos = (float) $f->ingresos;

            return $f;
        });

        $filas = $agrupar === 'dia'
            ? $filas->sortBy([['dia', 'desc'], ['unidades', 'desc']])->values()
            : $filas->sortByDesc('unidades')->values();

        // Totales y ranking siempre por producto (aunque se vea el detalle por día)
        $porProducto = $filas->groupBy('producto_id')->map(fn ($g) => (object) [
            'producto' => $g->first()->producto,
            'unidades' => (float) $g->sum('unidades'),
            'ingresos' => (float) $g->sum('ingresos'),
        ]);

        $totalUnidades = (float) $porProducto->sum('unidades');
        $totalIngresos = (float) $porProducto->sum('ingresos');
        $masVendido = $porProducto->sortByDesc('unidades')->first();
        $masIngresos = $porProducto->sortByDesc('ingresos')->first();
        $maxUnidades = (float) ($filas->max('unidades') ?: 1);

        $productos = Producto::orderBy('nombre')->get(['id', 'nombre']);
        $categorias = Producto::whereNotNull('categoria')->where('categoria', '!=', '')->distinct()->orderBy('categoria')->pluck('categoria');

        return view('reportes.ventas-productos', [
            'filas' => $filas,
            'inicio' => $inicio,
            'fin' => $fin,
            'agrupar' => $agrupar,
            'totalUnidades' => $totalUnidades,
            'totalIngresos' => $totalIngresos,
            'productosDistintos' => $porProducto->count(),
            'masVendido' => $masVendido,
            'masIngresos' => $masIngresos,
            'maxUnidades' => $maxUnidades,
            'productos' => $productos,
            'categorias' => $categorias,
        ]);
    }
}
