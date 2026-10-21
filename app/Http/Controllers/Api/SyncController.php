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
                'ordenes' => 'required|array|max:200',
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
                'ordenes.*.referencia_pago' => 'nullable|string|max:100',
                'ordenes.*.fecha_hora' => 'required|date',
                'ordenes.*.detalles' => 'required|array',
                'ordenes.*.detalles.*.producto_nombre' => 'required|string|max:255',
                'ordenes.*.detalles.*.precio_unitario' => 'required|numeric',
                'ordenes.*.detalles.*.cantidad' => 'required|numeric',
                'ordenes.*.detalles.*.subtotal' => 'required|numeric',
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
                    // La app sincroniza primero los clientes locales y manda el ID real del
                    // servidor. Antes, con is_local = true, ese ID se tomaba como una cédula y
                    // se creaba un cliente duplicado por cada orden.
                    $clienteId = null;
                    $clienteRef = (string) ($ordenData['cliente_id'] ?? '');
                    if ($clienteRef !== '' && ctype_digit($clienteRef)
                        && Cliente::whereKey((int) $clienteRef)->exists()) {
                        $clienteId = (int) $clienteRef;
                    }

                    // Crear orden
                    // `id` no es asignable en masa: se fija explícitamente para conservar el UUID de la app
                    $ordenId = (string) $ordenData['id'];
                    $orden = (new Orden)->forceFill([
                        'id' => $ordenId,
                        'numero_recibo' => $ordenData['numero_recibo'],
                        'user_id' => auth()->id(), // La ruta exige autenticación (auth:sanctum)
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
                        'referencia_pago' => $ordenData['referencia_pago'] ?? null,
                        'turno' => $ordenData['turno'] ?? null,
                        'fecha_hora' => $ordenData['fecha_hora'],
                        'notas' => $ordenData['notas'] ?? null,
                        'synced_at' => now(),
                        'is_local' => false,
                    ]);
                    $orden->save();

                    // Crear detalles de orden
                    foreach ($ordenData['detalles'] as $detalleData) {
                        DetalleOrden::create([
                            'id' => $detalleData['id'] ?? Str::uuid(),
                            'orden_id' => $ordenId,
                            'producto_id' => $detalleData['producto_id'] ?? null,
                            'producto_nombre' => $detalleData['producto_nombre'],
                            'precio_unitario' => $detalleData['precio_unitario'],
                            'cantidad' => $detalleData['cantidad'],
                            'subtotal' => $detalleData['subtotal'],
                            'descuento' => 0,
                            'total' => $detalleData['subtotal'],
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
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar órdenes.',
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
