<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clientes = Cliente::all();

        return response()->json([
            'data' => $clientes,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
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

            $cliente = Cliente::create($validated);

            return response()->json($cliente, 201);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'error' => 'Error al crear cliente.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $cliente = Cliente::findOrFail($id);

        return response()->json($cliente);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'apellido' => 'required|string|max:255',
                'cedula' => 'required|string|max:50|unique:clientes,cedula,'.$id,
                'telefono' => 'nullable|string|max:50',
                'correo' => 'nullable|email|max:255',
            ]);

            $cliente = Cliente::findOrFail($id);
            $cliente->update($validated);

            return response()->json($cliente);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'error' => 'Error al actualizar cliente.',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $cliente = Cliente::findOrFail($id);
            $cliente->delete();

            return response()->json(['message' => 'Cliente eliminado'], 200);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'error' => 'Error al eliminar cliente.',
            ], 500);
        }
    }
}
