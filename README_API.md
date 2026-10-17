# SaveCoin API - Laravel Backend para App Mobile Android

## Descripción del Proyecto

Este proyecto Laravel está diseñado como backend API para una aplicación móvil Android de gestión financiera (SaveCoin). Proporciona endpoints RESTful para autenticación, gestión de billeteras, transacciones y categorías.

## Características

- **Autenticación**: Registro, login y logout con Laravel Sanctum
- **Gestión de Billeteras**: Crear, leer, actualizar y eliminar billeteras de usuarios
- **Transacciones**: Registro de ingresos, gastos y transferencias
- **Categorías**: Clasificación personalizada de transacciones por ingresos y gastos
- **CORS**: Configurado para permitir conexiones desde aplicaciones móviles
- **API RESTful**: Estructura estándar de endpoints JSON

## Requisitos

- PHP 8.4+
- Composer
- MySQL/SQLite
- Laravel 13.x

## Instalación

1. Clonar el repositorio o copiar los archivos a `c:/wamp64/www/savecoin`
2. Instalar dependencias:
   ```bash
   composer install
   ```
3. Configurar archivo `.env` con credenciales de base de datos
4. Ejecutar migraciones:
   ```bash
   php artisan migrate
   ```

## Estructura de Base de Datos

### Tablas Principales

**users**: Usuarios del sistema
- id, name, email, password, timestamps

**wallets**: Billeteras de los usuarios
- id, user_id, name, description, balance, currency, type, is_active, timestamps

**transactions**: Transacciones financieras
- id, wallet_id, category_id, description, amount, type, transaction_date, reference, metadata, timestamps

**categories**: Categorías de transacciones
- id, user_id, name, description, type, icon, color, is_active, timestamps

## Endpoints API

### Autenticación

- `POST /api/register` - Registro de nuevo usuario
- `POST /api/login` - Inicio de sesión
- `POST /api/logout` - Cierre de sesión (requiere autenticación)

### Usuarios (Requieren autenticación)

- `GET /api/users` - Listar todos los usuarios
- `GET /api/users/{id}` - Obtener usuario específico
- `PUT /api/users/{id}` - Actualizar usuario
- `DELETE /api/users/{id}` - Eliminar usuario

### Billeteras (Requieren autenticación)

- `GET /api/wallets` - Listar billeteras del usuario
- `POST /api/wallets` - Crear nueva billetera
- `GET /api/wallets/{id}` - Obtener billetera específica
- `PUT /api/wallets/{id}` - Actualizar billetera
- `DELETE /api/wallets/{id}` - Eliminar billetera

### Transacciones (Requieren autenticación)

- `GET /api/transactions` - Listar transacciones del usuario
- `POST /api/transactions` - Crear nueva transacción
- `GET /api/transactions/{id}` - Obtener transacción específica
- `PUT /api/transactions/{id}` - Actualizar transacción
- `DELETE /api/transactions/{id}` - Eliminar transacción

### Categorías (Requieren autenticación)

- `GET /api/categories` - Listar categorías del usuario
- `POST /api/categories` - Crear nueva categoría
- `GET /api/categories/{id}` - Obtener categoría específica
- `PUT /api/categories/{id}` - Actualizar categoría
- `DELETE /api/categories/{id}` - Eliminar categoría

## Autenticación

La API utiliza Laravel Sanctum para autenticación basada en tokens. Para acceder a los endpoints protegidos:

1. Iniciar sesión con `/api/login` para obtener el token
2. Incluir el token en el header de las solicitudes:
   ```
   Authorization: Bearer {token}
   ```

## Configuración CORS

El archivo `config/cors.php` está configurado para aceptar solicitudes desde cualquier origen, lo que permite conexiones desde aplicaciones móviles Android.

## Desarrollo

### Ejecutar servidor de desarrollo:
```bash
php artisan serve
```

### Ver rutas disponibles:
```bash
php artisan route:list --path=api
```

### Ejecutar pruebas:
```bash
php artisan test
```

## Estructura del Proyecto

```
savecoin/
├── app/
│   ├── Http/Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── WalletController.php
│   │   ├── TransactionController.php
│   │   └── CategoryController.php
│   └── Models/
│       ├── User.php
│       ├── Wallet.php
│       ├── Transaction.php
│       └── Category.php
├── database/
│   ├── migrations/
│   │   ├── create_wallets_table.php
│   │   ├── create_transactions_table.php
│   │   └── create_categories_table.php
│   └── factories/
├── routes/
│   ├── api.php
│   └── web.php
└── config/
    └── cors.php
```

## Tecnologías Utilizadas

- Laravel 13.x
- Laravel Sanctum (autenticación API)
- Eloquent ORM
- MySQL/SQLite
- PHP 8.4

## Notas para Desarrolladores

- El proyecto sigue las convenciones de Laravel Boost
- Todos los controladores API están en `app/Http/Controllers/Api/`
- Los modelos incluyen relaciones Eloquent apropiadas
- Las migraciones incluyen claves foráneas y restricciones
- El código ha sido formateado con Laravel Pint
