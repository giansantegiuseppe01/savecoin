<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClienteController; // ✅ Importado correctamente
use App\Http\Controllers\Api\InsumoController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas (sin autenticación): solo login y health check.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/health', [SyncController::class, 'getHealth']);

// Rutas que requieren un token válido de Sanctum.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
});

// Rutas solo para administradores.
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // El registro de usuarios ya no es público: solo un admin puede crear cuentas.
    Route::post('/register', [AuthController::class, 'register']);

    // User routes
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Wallet routes
    Route::apiResource('wallets', WalletController::class);

    // Transaction routes
    Route::apiResource('transactions', TransactionController::class);

    // Category routes
    Route::apiResource('categories', CategoryController::class);

    // Eliminar clientes: solo administradores
    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy'])
        ->whereNumber('id')
        ->name('api.clientes.destroy');
});

// POS routes for mobile app (admin and employees)
Route::middleware(['auth:sanctum', 'pos'])->group(function () {
    // Productos y configuración para la sincronización inicial (ahora requieren login)
    Route::get('/pos/productos', [PosController::class, 'productos']);
    Route::get('/configuracion', function () {
        $config = Configuracion::first();

        return response()->json([
            'tasa_cambio' => $config ? $config->tasa_cambio : 7.5,
            'last_sync_at' => now(),
        ]);
    });

    // Clientes para el POS móvil (el borrado queda reservado a administradores)
    Route::apiResource('clientes', ClienteController::class)
        ->except(['destroy'])
        ->names([
            'index' => 'api.clientes.index',
            'store' => 'api.clientes.store',
            'show' => 'api.clientes.show',
            'update' => 'api.clientes.update',
        ]);

    Route::post('/pos/ordenes', [PosController::class, 'crearOrden']);
    Route::get('/pos/ordenes', [PosController::class, 'index']);
    Route::put('/pos/ordenes/{id}', [PosController::class, 'updateOrden']);
    Route::get('/pos/ordenes/{id}', [PosController::class, 'getOrden']);

    // Turno routes for POS
    Route::post('/turnos/abrir', [TurnoController::class, 'abrirTurno']);
    Route::post('/turnos/cerrar', [TurnoController::class, 'cerrarTurno']);
    Route::get('/turnos/activo', [TurnoController::class, 'getTurnoActivo']);
    Route::get('/turnos', [TurnoController::class, 'index']);
    Route::get('/turnos/{id}', [TurnoController::class, 'show'])->whereNumber('id');

    // Sync routes for mobile app
    Route::post('/sync/ordenes', [SyncController::class, 'syncOrdenes']);

    // Insumo routes (stock-alerts va primero: si no, {id} lo capturaría)
    Route::get('/insumos/stock-alerts', [InsumoController::class, 'stockAlerts']);
    Route::get('/insumos/{id}', [InsumoController::class, 'show'])->whereNumber('id');
});
