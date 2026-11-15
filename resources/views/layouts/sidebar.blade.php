<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SaveCoin - Sistema de Restaurante')</title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Tipografía -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tema SaveCoin -->
    <link rel="stylesheet" href="{{ asset('css/savecoin.css') }}?v={{ @filemtime(public_path('css/savecoin.css')) }}">
    
    @stack('styles')
</head>
<body>
    <!-- Sidebar Derecho -->
    <nav class="sidebar" id="sidebar">
        <div class="brand">
            <i class="bi bi-cash-coin"></i> SaveCoin POS
        </div>
        
        <div class="user-info">
            <i class="bi bi-person-circle me-2"></i>
            <strong>{{ Auth::check() ? Auth::user()->name : 'Usuario' }}</strong>
            <br>
            <small>{{ Auth::check() ? Auth::user()->email : '' }}</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}" 
                   href="{{ route('dashboard.index') }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('recibos.*') ? 'active' : '' }}" 
                   href="{{ route('recibos.index') }}">
                    <i class="bi bi-receipt"></i> Recibos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}"
                   href="{{ route('reportes.ventas-productos') }}">
                    <i class="bi bi-bar-chart-line"></i> Ventas por producto
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventario.*') ? 'active' : '' }}" 
                   href="{{ route('inventario.index') }}">
                    <i class="bi bi-box-seam"></i> Inventario
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('productos.*') ? 'active' : '' }}" 
                   href="{{ route('productos.index') }}">
                    <i class="bi bi-basket"></i> Productos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}" 
                   href="{{ route('clientes.index') }}">
                    <i class="bi bi-people"></i> Clientes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}" 
                   href="{{ route('usuarios.index') }}">
                    <i class="bi bi-person-gear"></i> Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('libro-contable.*') ? 'active' : '' }}"
                   href="{{ route('libro-contable.index') }}">
                    <i class="bi bi-book"></i> Libro Contable
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventario.bitacora') ? 'active' : '' }}"
                   href="{{ route('inventario.bitacora') }}">
                    <i class="bi bi-journal-text"></i> Bitácora
                </a>
            </li>
            <li class="nav-item mt-3">
                <hr class="border-light">
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('configuracion.*') ? 'active' : '' }}" 
                   href="{{ route('configuracion.index') }}">
                    <i class="bi bi-gear"></i> Configuración
                </a>
            </li>
        </ul>
        
        <div class="mt-4 px-3">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-light w-100">
                    <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                </button>
            </form>
        </div>
    </nav>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Contenido Principal -->
    <div class="main-content">
        <div class="topbar mb-3 rounded">
            <button type="button" class="btn btn-sm" id="btnMenu" aria-label="Abrir menú">
                <i class="bi bi-list fs-5"></i>
            </button>
            <strong><i class="bi bi-cash-coin text-warning"></i> SaveCoin POS</strong>
        </div>
        @yield('content')
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const alternar = (abrir) => {
                sidebar.classList.toggle('show', abrir);
                overlay.classList.toggle('show', abrir);
            };
            document.getElementById('btnMenu').addEventListener('click', () => alternar(!sidebar.classList.contains('show')));
            overlay.addEventListener('click', () => alternar(false));
            sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', () => alternar(false)));
        })();
    </script>

    @stack('scripts')
</body>
</html>
