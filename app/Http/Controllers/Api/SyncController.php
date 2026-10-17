<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\DetalleOrden;
use App\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    public function syncOrdenes(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ordenes' => 'required|array',
                'ordenes.*.id' => 'required|string',
                'ordenes.*.numero_recibo' => 'required|string',
                'ordenes.*.cliente_id' => 'nullable|string',
                'ordenes.*.cliente_nombre' => 'required|string',
                'ordenes.*.tipo' => 'required|in:venta,reembolso',
                'ordenes.*.subtotal' => 'required|numeric',
                'ordenes.*.descuento' => 'required|numeric',
                'ordenes.*.impuesto' => 'required|numeric',
                'ordenes.*.total' => 'required|numeric',
                'ordenes.*.tasa_cambio' => 'required|numeric',
                'ordenes.*.aplica_iva' => 'required|boolean',
                'ordenes.*.estado' => 'required|in:abierta,cerrada,anulada',
                'ordenes.*.metodo_pago' => 'nullable|in:efectivo,tarjeta,transferencia,mixto',
                'ordenes.*.fecha_hora' => 'required|date',
                'ordenes.*.detalles' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            foreach ($request->ordenes as $ordenData) {
                // Verificar si la orden ya existe
                $ordenExistente = Orden::where('numero_recibo', $ordenData['numero_recibo'])->first();

                if (! $ordenExistente) {
                    // Crear o buscar cliente si es local
                    $clienteId = null;
                    if (! empty($ordenData['cliente_id']) && $ordenData['is_local'] ?? false) {
                        $cliente = Cliente::where('cedula', $ordenData['cliente_nombre'])->first();
                        if (! $cliente) {
                            $cliente = Cliente::create([
                                'nombre' => explode(' ', $ordenData['cliente_nombre'])[0] ?? 'Cliente',
                                'apellido' => explode(' ', $ordenData['cliente_nombre'])[1] ?? '',
                                'cedula' => $ordenData['cliente_id'],
                                'is_local' => true,
                            ]);
                        }
                        $clienteId = $cliente->id;
                    } elseif (! empty($ordenData['cliente_id'])) {
                        $clienteId = $ordenData['cliente_id'];
                    }

                    // Crear orden
                    $orden = Orden::create([
                        'id' => $ordenData['id'], // UUID desde la app móvil
                        'numero_recibo' => $ordenData['numero_recibo'],
                        'user_id' => auth()->id() ?? 1, // Usuario por defecto si no hay auth
                        'cliente_id' => $clienteId,
                        'cliente_nombre' => $ordenData['cliente_nombre'],
                        'tipo' => $ordenData['tipo'],
                        'subtotal' => $ordenData['subtotal'],
                        'descuento' => $ordenData['descuento'],
                        'impuesto' => $ordenData['impuesto'],
                        'total' => $ordenData['total'],
                        'tasa_cambio' => $ordenData['tasa_cambio'],
                        'aplica_iva' => $ordenData['aplica_iva'] ?? true,
                        'costo_insumos' => 0,
                        'beneficio_bruto' => $ordenData['total'] - $ordenData['subtotal'],
                        'estado' => $ordenData['estado'],
                        'metodo_pago' => $ordenData['metodo_pago'] ?? null,
                        'turno' => $ordenData['turno'] ?? null,
                        'fecha_hora' => $ordenData['fecha_hora'],
                        'notas' => $ordenData['notas'] ?? null,
                        'synced_at' => now(),
                        'is_local' => false,
                    ]);

                    // Crear detalles de orden
                    foreach ($ordenData['detalles'] as $detalleData) {
                        DetalleOrden::create([
                            'id' => $detalleData['id'] ?? Str::uuid(),
                            'orden_id' => $orden->id,
                            'producto_id' => $detalleData['producto_id'] ?? null,
                            'producto_nombre' => $detalleData['producto_nombre'],
                            'precio_unitario' => $detalleData['precio_unitario'],
                            'cantidad' => $detalleData['cantidad'],
                            'subtotal' => $detalleData['subtotal'],
                            'notas' => $detalleData['notas'] ?? null,
                            'fue_modificado' => $detalleData['fue_modificado'] ?? false,
                            'synced_at' => now(),
                            'is_local' => false,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Órdenes sincronizadas exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar órdenes: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getHealth()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
