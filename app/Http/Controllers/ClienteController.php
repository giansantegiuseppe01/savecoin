<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::query();

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('apellido', 'like', "%{$buscar}%")
                    ->orWhere('cedula', 'like', "%{$buscar}%")
                    ->orWhere('telefono', 'like', "%{$buscar}%")
                    ->orWhere('correo', 'like', "%{$buscar}%");
            });
        }

        // 1. Si la Petición es de la API (Flutter) o solicita JSON
        if ($request->wantsJson() || $request->is('api/*')) {
            $clientes = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'data' => $clientes,
            ]);
        }

        // 2. Si es navegación convencional desde la Web de Laravel
        $clientes = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('clientes.index', compact('clientes'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'apellido' => 'required|string|max:255',
                'cedula' => 'required|string|max:50|unique:clientes',
                'telefono' => 'nullable|string|max:50',
                'correo' => 'nullable|email|max:255',
            ]);

            Cliente::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Cliente creado exitosamente.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear cliente.',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:clientes',
                'nombre' => 'required|string|max:255',
                'apellido' => 'required|string|max:255',
                'cedula' => 'required|string|max:50|unique:clientes,cedula,'.$request->id,
                'telefono' => 'nullable|string|max:50',
                'correo' => 'nullable|email|max:255',
            ]);

            $cliente = Cliente::findOrFail($validated['id']);
            $cliente->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado exitosamente.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);

            if ($cliente->ordens()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el cliente porque tiene órdenes asociadas.',
                ], 400);
            }

            $cliente->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cliente eliminado exitosamente.',
            ]);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cliente.',
            ], 500);
        }
    }

    public function estadisticas()
    {
        $clienteDelMes = Cliente::withCount('ordens')
            ->whereHas('ordens', function ($query) {
                $query->whereMonth('fecha_hora', now()->month)
                    ->whereYear('fecha_hora', now()->year);
            })
            ->orderBy('ordens_count', 'desc')
            ->first();

        $topClientes = Cliente::withCount('ordens')
            ->orderBy('ordens_count', 'desc')
            ->limit(10)
            ->get();

        return view('clientes.estadisticas', compact('clienteDelMes', 'topClientes'));
    }
}
