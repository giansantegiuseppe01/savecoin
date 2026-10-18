<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::all();

        return view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        return view('usuarios.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'rol' => 'required|in:admin,empleado',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
        ]);

        User::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'rol' => $validated['rol'],
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
        ]);

        // Return JSON for AJAX requests
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
            ]);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado exitosamente');
    }

    public function show($id)
    {
        $usuario = User::findOrFail($id);

        // Return JSON for AJAX requests
        if (request()->expectsJson()) {
            return response()->json($usuario);
        }

        return view('usuarios.show', compact('usuario'));
    }

    public function edit($id)
    {
        $usuario = User::findOrFail($id);

        return view('usuarios.edit', compact('usuario'));
    }

    public function update(Request $request, $id)
    {
        $usuario = User::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$id,
            'password' => 'nullable|string|min:8',
            'rol' => 'required|in:admin,empleado',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
        ]);

        // No permitir que el último administrador se quite a sí mismo el rol de admin
        if ($validated['rol'] !== 'admin' && $usuario->isAdmin()
            && User::where('rol', 'admin')->count() <= 1) {
            $mensaje = 'No se puede quitar el rol al único administrador.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $revocarTokens = ! empty($validated['password']) || $validated['rol'] !== $usuario->rol;

        // Solo actualizar contraseña si se proporciona una nueva
        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $usuario->update($validated);

        if ($revocarTokens) {
            $usuario->tokens()->delete();
        }

        // Return JSON for AJAX requests
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
            ]);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado exitosamente');
    }

    public function destroy($id)
    {
        $usuario = User::findOrFail($id);

        // Un administrador no puede borrar su propia cuenta (evita quedarse sin acceso)
        if ($usuario->id === auth()->id()) {
            $mensaje = 'No puedes eliminar tu propia cuenta.';

            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $mensaje], 422);
            }

            return redirect()->route('usuarios.index')->with('error', $mensaje);
        }

        $usuario->tokens()->delete();
        $usuario->delete();

        // Return JSON for AJAX requests
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente',
            ]);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado exitosamente');
    }
}
