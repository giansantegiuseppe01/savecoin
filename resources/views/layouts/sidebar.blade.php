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
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            padding-top: 20px;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 15px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.1);
            transform: translateX(-5px);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }
        .sidebar .brand {
            color: #fff;
            font-size: 1.5rem;
            font-weight: bold;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar .brand i {
            color: #f39c12;
        }
        .sidebar .user-info {
            padding: 15px 20px;
            background: rgba(255,255,255,0.05);
            margin: 10px 15px;
            border-radius: 8px;
            color: #fff;
        }
        .sidebar .user-info small {
            color: rgba(255,255,255,0.6);
        }
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        .kpi-card {
            padding: 20px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: rotate(45deg);
        }
        .kpi-card .kpi-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .kpi-card .kpi-icon {
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            top: 15px;
            right: 15px;
        }
        .table {
            border-radius: 12px;
            overflow: hidden;
        }
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .btn-action {
            padding: 5px 10px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: scale(1.1);
        }
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-right: 0;
            }
        }
    </style>
    
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

    <!-- Contenido Principal -->
    <div class="main-content">
        @yield('content')
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    @stack('scripts')
</body>
</html>
