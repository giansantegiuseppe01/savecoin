<?php

use App\Http\Controllers\CategoriaLibroContableController; // ✅ IMPORTADO
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\LibroContableController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReciboController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Auth routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();

        $user = Auth::user();

        // Redirect based on user role
        if ($user->rol === 'admin') {
            return redirect()->intended(route('dashboard.index'));
        } else {
            // For employees, redirect to a POS-specific page or show access denied
            // For now, redirect to dashboard with a message
            return redirect()->intended(route('dashboard.index'));
        }
    }

    return back()->withErrors([
        'email' => 'Las credenciales no coinciden con nuestros registros.',
    ]);
})->name('login.submit');

Route::post('/logout', function () {
    auth()->logout();

    return redirect()->route('login');
})->name('logout');

// Dashboard routes (solo admin)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/pdf', [DashboardController::class, 'pdf'])->name('dashboard.pdf');
    Route::get('/api/colaboradores', [DashboardController::class, 'getColaboradores']);
});

// Dashboard management routes (solo admin)
Route::middleware(['auth', 'admin'])->group(function () {
    // Receipts routes
    Route::prefix('recibos')->name('recibos.')->group(function () {
        Route::get('/', [ReciboController::class, 'index'])->name('index');
        Route::get('/pdf', [ReciboController::class, 'pdf'])->name('pdf-list');
        Route::get('/{id}', [ReciboController::class, 'show'])->name('show');
        Route::get('/{id}/pdf', [ReciboController::class, 'pdfSingle'])->name('pdf-single');
        Route::post('/{id}/anular', [ReciboController::class, 'anular'])->name('anular');
        Route::post('/{id}/reembolso', [ReciboController::class, 'createReembolso'])->name('reembolso');
    });

    // Users routes
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('/create', [UsuarioController::class, 'create'])->name('create');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');
        Route::get('/{id}', [UsuarioController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [UsuarioController::class, 'edit'])->name('edit');
        Route::put('/{id}', [UsuarioController::class, 'update'])->name('update');
        Route::delete('/{id}', [UsuarioController::class, 'destroy'])->name('destroy');
    });

    // Clients routes
    Route::prefix('clientes')->name('clientes.')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('index');
        Route::post('/', [ClienteController::class, 'store'])->name('store');
        Route::put('/{id}', [ClienteController::class, 'update'])->name('update');
        Route::delete('/{id}', [ClienteController::class, 'destroy'])->name('destroy');
        Route::get('/estadisticas', [ClienteController::class, 'estadisticas'])->name('estadisticas');
    });

    // Inventory routes
    Route::prefix('inventario')->name('inventario.')->group(function () {
        Route::get('/', [InventarioController::class, 'index'])->name('index');
        Route::post('/', [InventarioController::class, 'store'])->name('store');
        Route::put('/', [InventarioController::class, 'update'])->name('update');
        Route::delete('/{id}', [InventarioController::class, 'destroy'])->name('destroy');
        Route::post('/agregar-stock', [InventarioController::class, 'agregarStock'])->name('agregar-stock');
        Route::get('/bitacora', [InventarioController::class, 'bitacora'])->name('bitacora');
        Route::get('/bitacora/pdf', [InventarioController::class, 'bitacoraPdf'])->name('bitacora-pdf');
        Route::get('/pdf', [InventarioController::class, 'pdf'])->name('pdf');
    });

    // Products routes
    Route::prefix('productos')->name('productos.')->group(function () {
        Route::get('/', [ProductoController::class, 'index'])->name('index');
        Route::post('/', [ProductoController::class, 'store'])->name('store');
        Route::get('/{id}', [ProductoController::class, 'show'])->name('show');
        Route::put('/{id}', [ProductoController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProductoController::class, 'destroy'])->name('destroy');
    });

    // API routes for productos
    Route::get('/api/productos/insumos', [ProductoController::class, 'getInsumos']);

    // Libro Contable routes
    Route::prefix('libro-contable')->name('libro-contable.')->group(function () {
        Route::get('/', [LibroContableController::class, 'index'])->name('index');
        Route::post('/', [LibroContableController::class, 'store'])->name('store');
        Route::delete('/{id}', [LibroContableController::class, 'destroy'])->name('destroy');
        Route::get('/pdf', [LibroContableController::class, 'pdf'])->name('pdf');
    });

    // Categorías Libro Contable routes
    Route::prefix('categorias-libro-contable')->name('categorias-libro-contable.')->group(function () {
        Route::get('/', [CategoriaLibroContableController::class, 'index'])->name('index');
        Route::post('/', [CategoriaLibroContableController::class, 'store'])->name('store');
        Route::put('/{id}', [CategoriaLibroContableController::class, 'update'])->name('update');
        Route::delete('/{id}', [CategoriaLibroContableController::class, 'destroy'])->name('destroy');
    });

    // Configuracion routes
    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        Route::get('/', [ConfiguracionController::class, 'index'])->name('index');
        Route::put('/', [ConfiguracionController::class, 'update'])->name('update');
    });
});
