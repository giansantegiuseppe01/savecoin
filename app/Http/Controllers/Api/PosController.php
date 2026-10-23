<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BitacoraInventario;
use App\Models\Configuracion;
use App\Models\DetalleOrden;
use App\Models\Orden;
use App\Models\Producto;
use App\Models\Turno;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function productos(): JsonResponse
    {
        $productos = Producto::where('activo', true)
            ->with(['insumos', 'componentes'])
            ->get();

        return response()->json($productos);
    }

    public function crearOrden(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'id' => 'nullable|string', // UUID opcional desde móvil
                'numero_recibo' => 'nullable|string', // Número de recibo opcional, se generará desde el ID
                'cliente_id' => 'nullable|exists:clientes,id',
                'cliente_nombre' => 'nullable|string|max:255',
                'estado' => 'nullable|in:abierta,cerrada,anulada',
                'metodo_pago' => 'nullable|in:efectivo,tarjeta,transferencia,mixto', // Cambiado a nullable
                'turno' => 'nullable|string|in:mañana,tarde,noche', // Cambiado a nullable
                'descuento' => 'nullable|numeric|min:0',
                'impuesto' => 'nullable|numeric|min:0',
                'aplica_iva' => 'nullable|boolean',
                'notas' => 'nullable|string',
                'referencia_pago' => 'nullable|string|max:100',
                'detalles' => 'nullable|array', // Cambiado de required a nullable
                'detalles.*.id' => 'nullable|string', // UUID opcional desde móvil
                'detalles.*.producto_id' => 'required|exists:productos,id',
                'detalles.*.cantidad' => 'required|integer|min:1',
                'detalles.*.precio_unitario' => 'required|numeric|min:0',
                'detalles.*.descuento' => 'nullable|numeric|min:0',
            ]);

            // Verificar turno activo (se requiere tanto para abrir como cerrar)
            $turnoActivo = Turno::where('user_id', Auth::id())
                ->where('estado', 'abierto')
                ->first();

            if (! $turnoActivo) {
                DB::rollBack();

                return response()->json([
                    'message' => 'No tienes un turno abierto. Debes abrir un turno antes de crear órdenes.',
                ], 400);
            }

            // Obtener estado de la orden
            $estado = $validated['estado'] ?? 'abierta';

            // Calcular totales
            $subtotal = 0;
            $descuentoTotal = $validated['descuento'] ?? 0;
            $costoInsumosTotal = 0;
            $advertencias = [];

            foreach ($validated['detalles'] as $detalle) {
                $subtotal += $detalle['precio_unitario'] * $detalle['cantidad'];
            }

            // Si la orden está cerrada, deducir stock y calcular costo
            if ($estado === 'cerrada') {
                foreach ($validated['detalles'] as $detalle) {
                    $producto = Producto::with(['insumos', 'componentes'])->findOrFail($detalle['producto_id']);

                    $costoInsumosTotal += app(InventarioService::class)->descontarVenta(
                        $producto,
                        (float) $detalle['cantidad'],
                        null,
                        'nueva orden',
                        Auth::id(),
                        $advertencias,
                    );
                }
            }

            // Calcular impuesto solo si aplica IVA
            $aplicaIva = $validated['aplica_iva'] ?? false;
            $impuesto = $aplicaIva ? ($subtotal - $descuentoTotal) * 0.16 : 0;
            $total = $subtotal - $descuentoTotal + $impuesto;
            $beneficioBruto = $total - $costoInsumosTotal;

            // Obtener tasa de cambio
            $tasaCambio = Configuracion::getTasaCambio();

            // Crear orden (con numero_recibo temporal, se actualizará después)
            $orden = Orden::create([
                'numero_recibo' => 'TEMP-'.time(), // Valor temporal hasta que se genere el ID
                'user_id' => Auth::id(),
                'turno_id' => $turnoActivo->id, // Asignar turno activo
                'cliente_id' => $validated['cliente_id'] ?? null,
                'cliente_nombre' => $validated['cliente_nombre'] ?? null,
                'tipo' => 'venta',
                'subtotal' => $subtotal,
                'descuento' => $descuentoTotal,
                'impuesto' => $impuesto,
                'total' => $total,
                'tasa_cambio' => $tasaCambio,
                'aplica_iva' => $aplicaIva,
                'costo_insumos' => $costoInsumosTotal,
                'beneficio_bruto' => $beneficioBruto,
                'estado' => $estado,
                'metodo_pago' => $validated['metodo_pago'] ?? null, // Solo asignar si se proporciona
                'turno' => null, // No usar este campo, usar turno_id
                'fecha_hora' => now(),
                'notas' => $validated['notas'] ?? null,
                'referencia_pago' => $validated['referencia_pago'] ?? null,
                'synced_at' => now(),
                'is_local' => false,
            ]);

            // Generar numero_recibo basado en el ID serial
            $orden->numero_recibo = 'ORD-'.$orden->id;
            $orden->save();

            // Actualizar orden_id en bitácoras si la orden está cerrada
            if ($estado === 'cerrada') {
                BitacoraInventario::where('orden_id', null)
                    ->where('user_id', Auth::id())
                    ->update(['orden_id' => $orden->id]);
            }

            // Crear detalles solo si existen
            if (! empty($validated['detalles'])) {
                foreach ($validated['detalles'] as $detalle) {
                    DetalleOrden::create([
                        'orden_id' => $orden->id,
                        'producto_id' => $detalle['producto_id'],
                        'producto_nombre' => Producto::findOrFail($detalle['producto_id'])->nombre,
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'subtotal' => $detalle['precio_unitario'] * $detalle['cantidad'],
                        'descuento' => $detalle['descuento'] ?? 0,
                        'total' => ($detalle['precio_unitario'] * $detalle['cantidad']) - ($detalle['descuento'] ?? 0),
                        'synced_at' => now(),
                        'is_local' => false,
                    ]);
                }
            }

            DB::commit();

            $responseData = [
                'message' => 'Orden creada exitosamente',
                'data' => $orden->load('detalles.producto'),
            ];

            if (! empty($advertencias)) {
                $responseData['advertencias'] = $advertencias;
            }

            return response()->json($responseData, 201);

        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear orden.',
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        $query = Orden::with(['user', 'cliente', 'detalles.producto'])
            ->orderBy('id', 'desc');

        // Los empleados solo ven sus propias órdenes; el admin ve todas.
        if (! Auth::user()->isAdmin()) {
            $query->where('user_id', Auth::id());
        }

        $ordens = $query->get();

        return response()->json([
            'data' => $ordens,
        ]);
    }

    public function getOrden($id): JsonResponse
    {
        $orden = Orden::with(['user', 'cliente', 'detalles.producto'])->findOrFail($id);

        if (! $this->puedeAccederOrden($orden)) {
            return response()->json(['message' => 'No autorizado para ver esta orden.'], 403);
        }

        return response()->json([
            'data' => $orden,
        ]);
    }

    public function updateOrden(Request $request, $id): JsonResponse
    {
        try {
            Log::info('updateOrden llamado', ['id' => $id, 'user_id' => Auth::id()]);

            DB::beginTransaction();

            // Intentar encontrar la orden por el ID (puede venir como string desde móvil)
            $orden = Orden::where('id', $id)->first();

            if (! $orden) {
                // La orden no existe en el servidor, crearla nueva
                // No asignamos el ID, dejamos que la BD genere el autoincrement
                Log::info('Orden no existe, creando nueva');
                $orden = new Orden;
                $orden->numero_recibo = 'TEMP-'.time(); // Temporal hasta tener el ID
                $orden->cliente_id = $request->input('cliente_id');
                $orden->cliente_nombre = $request->input('cliente_nombre');
                $orden->tipo = $request->input('tipo', 'venta');
                $orden->tasa_cambio = $request->input('tasa_cambio', 1);
                $orden->fecha_hora = $request->input('fecha_hora', now());
                $orden->is_local = true;
            } else {
                Log::info('Orden encontrada', ['orden_id' => $orden->id, 'estado' => $orden->estado]);

                // Un empleado no puede modificar órdenes de otro empleado.
                if (! $this->puedeAccederOrden($orden)) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'No autorizado para modificar esta orden.',
                    ], 403);
                }
            }

            // ¿La orden ya estaba cerrada antes de esta petición? Sirve para descontar
            // el stock una sola vez, justo en la transición a "cerrada".
            $estabaCerrada = $orden->estado === 'cerrada';

            // Si la orden ya está en el servidor y está cerrada, no permitir actualización
            if ($orden->estado === 'cerrada' && ! $orden->is_local) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Esta orden ya está cerrada en el servidor.',
                ], 400);
            }

            $validated = $request->validate([
                'estado' => 'required|in:abierta,cerrada,anulada',
                'metodo_pago' => 'nullable|in:efectivo,tarjeta,transferencia,mixto',
                // Una transferencia no se puede cerrar sin su referencia bancaria
                'referencia_pago' => [
                    'nullable', 'string', 'max:100',
                    Rule::requiredIf(fn () => $request->input('estado') === 'cerrada'
                        && $request->input('metodo_pago') === 'transferencia'),
                ],
                'subtotal' => 'nullable|numeric|min:0',
                'descuento' => 'nullable|numeric|min:0',
                'impuesto' => 'nullable|numeric|min:0',
                'total' => 'nullable|numeric|min:0',
                'aplica_iva' => 'nullable|boolean',
                'turno' => 'nullable|string|in:mañana,tarde,noche',
                'is_local' => 'nullable|boolean',
                'detalles' => 'nullable|array',
                'detalles.*.producto_id' => 'required|integer',
                'detalles.*.producto_nombre' => 'required|string',
                'detalles.*.precio_unitario' => 'required|numeric|min:0',
                'detalles.*.cantidad' => 'required|numeric|min:0',
            ]);

            // Actualizar campos básicos
            $orden->estado = $validated['estado'];
            if (isset($validated['metodo_pago'])) {
                $orden->metodo_pago = $validated['metodo_pago'];
            }
            if (array_key_exists('referencia_pago', $validated)) {
                $orden->referencia_pago = $validated['referencia_pago'];
            }
            if (isset($validated['subtotal'])) {
                $orden->subtotal = $validated['subtotal'];
            }
            if (isset($validated['descuento'])) {
                $orden->descuento = $validated['descuento'];
            }
            if (isset($validated['impuesto'])) {
                $orden->impuesto = $validated['impuesto'];
            }
            if (isset($validated['total'])) {
                $orden->total = $validated['total'];
            }
            if (isset($validated['aplica_iva'])) {
                $orden->aplica_iva = $validated['aplica_iva'];
            }
            if (isset($validated['turno'])) {
                $orden->turno = $validated['turno'];
            }
            if (isset($validated['is_local'])) {
                $orden->is_local = $validated['is_local'];
            }

            // Si la orden se está cerrando ahora, deducir stock y registrar en bitácora.
            // Antes solo se hacía con is_local = true, así que las órdenes creadas con
            // conexión (is_local = false) se cerraban sin descontar inventario.
            if ($validated['estado'] === 'cerrada' && ! $estabaCerrada) {
                Log::info('Cerrando orden', ['orden_id' => $orden->id]);

                // Verificar turno activo
                $turnoActivo = Turno::where('user_id', Auth::id())
                    ->where('estado', 'abierto')
                    ->first();

                if (! $turnoActivo) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'No tienes un turno abierto.',
                    ], 400);
                }

                $orden->turno_id = $turnoActivo->id;
                $orden->user_id = Auth::id();
                $orden->is_local = false;
                $orden->synced_at = now();

                // Calcular costo de insumos y actualizar stock usando los detalles enviados
                $costoInsumosTotal = 0;
                $detalles = $validated['detalles'] ?? [];
                $advertencias = [];

                foreach ($detalles as $detalleData) {
                    $producto = Producto::with(['insumos', 'componentes'])->findOrFail($detalleData['producto_id']);

                    $costoInsumosTotal += app(InventarioService::class)->descontarVenta(
                        $producto,
                        (float) $detalleData['cantidad'],
                        $orden->id,
                        'recibo #'.$orden->numero_recibo,
                        Auth::id(),
                        $advertencias,
                    );
                }

                $orden->costo_insumos = $costoInsumosTotal;
                $orden->beneficio_bruto = $orden->total - $costoInsumosTotal;
            }

            Log::info('Guardando orden', ['orden_id' => $orden->id, 'estado' => $orden->estado]);
            $orden->save();
            Log::info('Orden guardada exitosamente', ['orden_id' => $orden->id]);

            // Generar numero_recibo basado en el ID serial si está vacío
            if (empty($orden->numero_recibo)) {
                $orden->numero_recibo = 'ORD-'.$orden->id;
                $orden->save();
            }

            // Crear o actualizar detalles de orden (para órdenes abiertas y cerradas)
            if (isset($validated['detalles'])) {
                // Eliminar detalles anteriores de esta orden
                DetalleOrden::where('orden_id', $orden->id)->delete();

                // Crear nuevos detalles
                foreach ($validated['detalles'] as $detalleData) {
                    DetalleOrden::create([
                        'orden_id' => $orden->id,
                        'producto_id' => $detalleData['producto_id'],
                        'producto_nombre' => $detalleData['producto_nombre'],
                        'precio_unitario' => $detalleData['precio_unitario'],
                        'cantidad' => $detalleData['cantidad'],
                        'subtotal' => $detalleData['precio_unitario'] * $detalleData['cantidad'],
                        'descuento' => $detalleData['descuento'] ?? 0,
                        'total' => ($detalleData['precio_unitario'] * $detalleData['cantidad']) - ($detalleData['descuento'] ?? 0),
                        'synced_at' => now(),
                        'is_local' => false,
                    ]);
                }
            }

            DB::commit();

            $responseData = [
                'message' => 'Orden actualizada exitosamente',
                'data' => $orden->load('detalles.producto'),
            ];

            // Agregar advertencias si hay stock insuficiente
            if (! empty($advertencias)) {
                $responseData['advertencias'] = $advertencias;
            }

            return response()->json($responseData, 200);

        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar orden.',
            ], 500);
        }
    }

    /**
     * El admin accede a todas las órdenes; un empleado solo a las suyas.
     */
    private function puedeAccederOrden(Orden $orden): bool
    {
        $user = Auth::user();

        return $user->isAdmin() || (int) $orden->user_id === (int) $user->id;
    }

    private function generarNumeroRecibo(): string
    {
        $ultimoRecibo = Orden::where('numero_recibo', 'like', 'V%')
            ->orderBy('id', 'desc')
            ->first();

        if ($ultimoRecibo) {
            $ultimoNumero = intval(substr($ultimoRecibo->numero_recibo, 1));
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return 'V'.str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);
    }
}
