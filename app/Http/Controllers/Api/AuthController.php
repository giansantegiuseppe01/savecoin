<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Crear una cuenta nueva. La ruta está protegida con auth:sanctum + admin,
     * por lo que solo un administrador puede elegir el rol de la cuenta.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'required|in:admin,empleado',
        ]);

        $user = User::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'rol' => $validated['rol'],
        ]);

        return response()->json([
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Hash::check se ejecuta siempre (aun sin usuario, contra un hash falso)
        // para no revelar por tiempo de respuesta si el correo existe.
        $hash = $user?->password ?? '$2y$12$lERZgbdkOOTJuQ/S8BY25uv7Z7hqO1UrzRHQKeOioFNMeqVhg2eJe';
        $passwordOk = Hash::check($validated['password'], $hash);

        if (! $user || ! $passwordOk) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        // Token de API con Sanctum (sin tocar la sesión web)
        $token = $user->createToken('mobile-app-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->nombre.' '.$user->apellido,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'rol' => $user->rol,
            ],
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoca únicamente el token con el que se hizo esta petición.
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
