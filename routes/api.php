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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication routes (no auth required for login/register)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// User routes (admin only)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
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
});

// POS routes for mobile app (admin and employees)
Route::middleware(['auth:sanctum', 'pos'])->group(function () {
    Route::post('/pos/ordenes', [PosController::class, 'store']);
    Route::get('/pos/ordenes', [PosController::class, 'index']);
    Route::put('/pos/ordenes/{id}', [PosController::class, 'updateOrden']);
    Route::get('/pos/ordenes/{id}', [PosController::class, 'getOrden']);

    // Turno routes for POS
    Route::post('/turnos/abrir', [TurnoController::class, 'abrirTurno']);
    Route::post('/turnos/cerrar', [TurnoController::class, 'cerrarTurno']);
    Route::get('/turnos/activo', [TurnoController::class, 'getTurnoActivo']);
    Route::get('/turnos', [TurnoController::class, 'index']);
    Route::get('/turnos/{id}', [TurnoController::class, 'show']);

    // Sync routes for mobile app
    Route::post('/sync/ordenes', [SyncController::class, 'syncOrdenes']);
    Route::get('/health', [SyncController::class, 'getHealth']);

    // Insumo routes
    Route::get('/insumos/{id}', [InsumoController::class, 'show']);
    Route::get('/insumos/stock-alerts', [InsumoController::class, 'stockAlerts']);
});

// Productos route accessible without auth for initial sync
Route::get('/pos/productos', [PosController::class, 'productos']);

// Cliente routes (sin autenticación para POS móvil)
Route::apiResource('clientes', ClienteController::class)->names([
    'index' => 'api.clientes.index',
    'store' => 'api.clientes.store',
    'show' => 'api.clientes.show',
    'update' => 'api.clientes.update',
    'destroy' => 'api.clientes.destroy',
]);

// Configuración route for mobile app
Route::get('/configuracion', function () {
    $config = Configuracion::first();

    return response()->json([
        'tasa_cambio' => $config ? $config->tasa_cambio : 7.5,
        'last_sync_at' => now(),
    ]);
});

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
