# SaveCoin Restaurant POS - Sistema de Gestión de Restaurante

## Descripción del Sistema

Sistema completo de gestión de restaurante y POS (Point of Sale) desarrollado en Laravel 11 con PostgreSQL, diseñado para controlar ventas, inventario de insumos, recetas de productos y generación de reportes financieros.

## Características Principales

### 1. Gestión de Inventario
- **Insumos**: Control de stock con precisión DECIMAL(12,4)
- **Recetas**: Relación productos-insumos para costos de producción
- **Bitácora de Inventario**: Registro automático de movimientos de stock

### 2. Sistema de Ventas POS
- **Creación de Órdenes**: API para app móvil con validación de stock
- **Control de Costos**: Cálculo automático de costo de insumos por venta
- **Múltiples Métodos de Pago**: Efectivo, tarjeta, transferencia, mixto
- **Gestión por Turnos**: Mañana, tarde, noche

### 3. Sistema de Recibos y Anulaciones
- **Generación de Recibos**: Numeración automática (V00000001)
- **Anulación de Recibos**: Revierte stock automáticamente
- **Reembolsos**: Creación de recibos negativos
- **Bitácora Automática**: Registro de todos los movimientos

### 4. Dashboard Financiero
- **KPIs en Tiempo Real**: Ventas brutas, netas, reembolsos, beneficios
- **Gráficas Dinámicas**: Chart.js para tendencias de ventas
- **Filtros Avanzados**: Por fecha, turno, colaborador
- **Desglose Diario**: Tabla detallada de métricas por día

## Requisitos Técnicos

- **PHP**: 8.4+
- **Laravel**: 13.x
- **Base de Datos**: PostgreSQL
- **Frontend**: Bootstrap 5 + Bootstrap Icons + Chart.js
- **Autenticación**: Laravel Sanctum

## Instalación

### 1. Configurar Base de Datos PostgreSQL

```bash
# Crear base de datos
createdb savecoin_restaurant

# Configurar .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=savecoin_restaurant
DB_USERNAME=postgres
DB_PASSWORD=tu_contraseña
```

### 2. Instalar Dependencias

```bash
composer install
npm install
```

### 3. Ejecutar Migraciones

```bash
php artisan migrate
```

### 4. Configurar Usuario Admin

```bash
php artisan tinker
>>> $user = \App\Models\User::create([
...     'name' => 'Admin',
...     'email' => 'admin@restaurant.com',
...     'password' => bcrypt('password'),
...     'rol' => 'admin'
... ]);
```

## Estructura de Base de Datos

### Tablas Principales

**users**: Usuarios del sistema
- Campos adicionales: `rol` (admin/empleado/cliente), `telefono`, `direccion`

**insumos**: Materias primas e ingredientes
- `costo_unitario` DECIMAL(12,4)
- `stock_actual` DECIMAL(12,4)
- `unidad_medida`, `stock_minimo`, `activo`

**productos**: Productos finales del menú
- `precio_venta` DECIMAL(12,4)
- `costo_produccion` DECIMAL(12,4)
- `categoria`, `imagen_url`, `preparado_en_cocina`

**producto_insumos**: Recetas (relación muchos a muchos)
- `cantidad_requerida` DECIMAL(12,4)
- `unidad_medida`

**ordens**: Órdenes/Recibos
- `numero_recibo` único
- `subtotal`, `descuento`, `impuesto`, `total` DECIMAL(12,4)
- `costo_insumos`, `beneficio_bruto` DECIMAL(12,4)
- `tipo` (venta/reembolso), `estado` (activa/completada/anulada)
- `metodo_pago`, `turno`, `fecha_hora`

**detalle_ordens**: Detalles de cada orden
- `cantidad`, `precio_unitario`, `subtotal`, `total` DECIMAL(12,4)

**bitacora_inventarios**: Registro de movimientos de stock
- `tipo_movimiento` (entrada/salida/ajuste/reembolso)
- `cantidad_anterior`, `cantidad_movimiento`, `cantidad_nueva` DECIMAL(12,4)
- `motivo`, `user_id`

## API Endpoints

### Autenticación (Sanctum)

- `POST /api/register` - Registro de usuarios
- `POST /api/login` - Inicio de sesión
- `POST /api/logout` - Cierre de sesión

### POS para App Móvil

- `GET /api/pos/productos` - Listar productos activos con insumos
- `POST /api/pos/ordenes` - Crear nueva orden
  - Valida stock disponible
  - Calcula costos automáticamente
  - Actualiza inventario
  - Registra en bitácora
- `GET /api/pos/ordenes/{id}` - Obtener orden específica

### Ejemplo de Creación de Orden

```json
POST /api/pos/ordenes
Authorization: Bearer {token}

{
  "metodo_pago": "efectivo",
  "turno": "tarde",
  "descuento": 0,
  "impuesto": 0,
  "cliente_nombre": "Juan Pérez",
  "detalles": [
    {
      "producto_id": 1,
      "cantidad": 2,
      "precio_unitario": 15.50,
      "descuento": 0
    },
    {
      "producto_id": 3,
      "cantidad": 1,
      "precio_unitario": 8.00,
      "descuento": 0
    }
  ]
}
```

## Rutas Web (Dashboard)

- `GET /dashboard` - Dashboard financiero con KPIs y gráficas
- `GET /recibos` - Listado de recibos con filtros
- `GET /recibos/{id}` - Detalle de recibo específico
- `POST /recibos/{id}/anular` - Anular recibo (revertir stock)
- `POST /recibos/{id}/reembolso` - Crear reembolso

## Lógica de Anulación de Recibos

### Proceso de Anulación

1. **Verificación**: Confirma que el recibo sea una venta activa
2. **Marcado**: Cambia estado a 'anulada'
3. **Reversión de Stock**: 
   - Por cada producto en la orden
   - Por cada insumo en la receta del producto
   - Calcula cantidad total a revertir
   - Suma al stock actual del insumo
4. **Bitácora**: Registra movimiento tipo 'reembolso' con:
   - Cantidad anterior y nueva
   - Motivo detallado
   - Usuario que realizó la acción

### Ejemplo de Código

```php
// En ReciboController.php
public function anular(Request $request, $id)
{
    DB::beginTransaction();
    
    $orden = Orden::with('detalles.producto')->findOrFail($id);
    $orden->estado = 'anulada';
    $orden->save();
    
    foreach ($orden->detalles as $detalle) {
        $producto = $detalle->producto;
        $insumosProducto = $producto->insumos;
        
        foreach ($insumosProducto as $insumoPivot) {
            $insumo = $insumoPivot;
            $cantidadTotal = $insumoPivot->pivot->cantidad_requerida * $detalle->cantidad;
            
            $stockAnterior = $insumo->stock_actual;
            $insumo->stock_actual += $cantidadTotal;
            $insumo->save();
            
            BitacoraInventario::create([
                'insumo_id' => $insumo->id,
                'orden_id' => $orden->id,
                'tipo_movimiento' => 'reembolso',
                'cantidad_anterior' => $stockAnterior,
                'cantidad_movimiento' => $cantidadTotal,
                'cantidad_nueva' => $insumo->stock_actual,
                'motivo' => 'Anulación de recibo #' . $orden->numero_recibo,
                'user_id' => Auth::id(),
            ]);
        }
    }
    
    DB::commit();
}
```

## Frontend - Bootstrap 5

### Dashboard View
- KPIs superiores con tarjetas coloridas
- Gráfica de líneas con Chart.js
- Filtros por fecha, turno, colaborador
- Tabla de desglose diario con totales

### Recibos View
- KPIs de contador de recibos
- Tabla paginada con acciones
- Filtros por tipo y estado
- Botón de anulación con confirmación

### Detalle de Recibo
- Información completa del recibo
- Resumen financiero detallado
- Lista de productos con cantidades
- Acciones de anulación/reembolso

## Precisión Financiera

Todos los campos monetarios utilizan `DECIMAL(12,4)`:
- Permite hasta 8 dígitos enteros y 4 decimales
- Ideal para cálculos financieros precisos
- Evita errores de redondeo de floating point

## Seguridad

- **Autenticación**: Laravel Sanctum para API
- **Autorización**: Middleware por roles
- **Validación**: Request validation en todos los endpoints
- **Transacciones**: DB::beginTransaction() para operaciones críticas
- **Soft Deletes**: Protección contra eliminación accidental

## Desarrollo

### Ejecutar Servidor de Desarrollo

```bash
php artisan serve
```

### Ejecutar Tests

```bash
php artisan test
```

### Formatear Código

```bash
vendor/bin/pint --format agent
```

## Arquitectura

```
app/
├── Http/Controllers/
│   ├── DashboardController.php      # Dashboard financiero
│   ├── ReciboController.php          # Gestión de recibos
│   └── Api/
│       └── PosController.php         # API para app móvil
├── Models/
│   ├── User.php                      # Usuarios con roles
│   ├── Insumo.php                    # Materias primas
│   ├── Producto.php                  # Productos del menú
│   ├── ProductoInsumo.php            # Recetas
│   ├── Orden.php                     # Órdenes/Recibos
│   ├── DetalleOrden.php              # Detalles de orden
│   └── BitacoraInventario.php       # Movimientos de stock
database/
└── migrations/                       # Migraciones con DECIMAL(12,4)
resources/
└── views/
    ├── layouts/
    │   └── app.blade.php            # Layout principal
    ├── dashboard/
    │   └── index.blade.php           # Dashboard con Chart.js
    └── recibos/
        ├── index.blade.php           # Listado de recibos
        └── show.blade.php            # Detalle de recibo
routes/
├── web.php                           # Rutas del dashboard
└── api.php                           # Rutas API con Sanctum
```

## Notas Importantes

1. **Configuración PostgreSQL**: Requiere configurar el archivo `.env` con credenciales de PostgreSQL
2. **Stock Management**: El sistema valida stock antes de permitir ventas
3. **Bitácora Automática**: Todos los movimientos de stock se registran automáticamente
4. **Anulaciones**: El proceso de anulación es reversible y completamente auditado
5. **Móvil**: La API está diseñada para integración con app móvil Android/iOS

## Próximos Pasos Sugeridos

1. Implementar sistema de roles y permisos más granular
2. Agregar reportes PDF de recibos
3. Implementar notificaciones de stock bajo
4. Agregar módulo de proveedores
5. Implementar sistema de reservas
6. Agregar integración con pasarelas de pago
7. Implementar dashboard para cocina (KDS)
8. Agregar análisis avanzado de ventas
