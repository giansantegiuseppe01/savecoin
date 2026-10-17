# SaveCoin POS - Aplicación Móvil

Sistema de Punto de Venta (POS) para restaurantes con soporte offline-first, impresión Bluetooth ESC/POS y sincronización con Laravel.

## Características

- **Offline-First**: Funciona sin conexión a internet
- **Impresión Bluetooth**: Compatibilidad con impresoras térmicas ESC/POS
- **Soporte Web**: Funciona en navegador con simulación de tickets
- **Multimoneda**: Soporte USD/VES con tasa de cambio configurable
- **Gestión de Clientes**: Asignación de clientes a órdenes
- **Comandas de Cocina**: Envío e impresión de comandas
- **Sincronización Automática**: Sincronización de datos cuando hay conexión
- **Layout LithosPOS**: Interfaz optimizada para restaurantes

## Requisitos Previos

- Flutter SDK 3.0+
- Dart SDK 3.0+
- Android Studio / VS Code con extensión Flutter
- Dispositivo Android o iOS para pruebas
- Laravel backend ejecutándose en el servidor

## Instalación

### 1. Clonar el Proyecto

```bash
cd mobile_app/savecoin_pos
```

### 2. Instalar Dependencias

```bash
flutter pub get
```

### 3. Generar Adapters de Hive

```bash
flutter pub run build_runner build --delete-conflicting-outputs
```

### 4. Configurar Permisos

#### Android

Agregar a `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.BLUETOOTH" />
<uses-permission android:name="android.permission.BLUETOOTH_ADMIN" />
<uses-permission android:name="android.permission.BLUETOOTH_CONNECT" />
<uses-permission android:name="android.permission.BLUETOOTH_SCAN" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.INTERNET" />
```

#### iOS

Agregar a `ios/Runner/Info.plist`:

```xml
<key>NSBluetoothAlwaysUsageDescription</key>
<string>Esta app necesita Bluetooth para conectar impresoras térmicas</string>
<key>NSBluetoothPeripheralUsageDescription</key>
<string>Esta app necesita Bluetooth para conectar impresoras térmicas</string>
<key>NSLocationWhenInUseUsageDescription</key>
<string>Esta app necesita ubicación para escanear dispositivos Bluetooth</string>
```

### 5. Ejecutar la Aplicación

#### Mobile (Android/iOS)

```bash
flutter run
```

#### Web

```bash
flutter run -d chrome
```

**Nota para Web**: La plataforma Web no soporta Bluetooth nativo. En su lugar, la aplicación muestra un modal de simulación para previsualizar los tickets (comandas, pre-cuenta, ticket final) antes de "imprimir".

## Configuración

### Configuración del Servidor

1. Abre la aplicación
2. Ve a **Configuración** (icono de engranaje)
3. Configura la **URL del Servidor** (ej: `http://192.168.1.100:8000/api`)
4. Configura el **Token de API** si es necesario
5. Presiona **Sincronizar Todo** para cargar productos y clientes

### Configuración de Tasa de Cambio

1. En **Configuración**, actualiza la **Tasa de Cambio** (USD a VES)
2. Puedes sincronizarla desde el servidor o configurarla manualmente

### Configuración de Impresora Bluetooth

**Solo Mobile (Android/iOS)**:

1. En **Configuración**, ve a **Impresora Bluetooth**
2. Presiona **Buscar Impresoras**
3. Selecciona tu impresora térmica de la lista
4. La impresora se guardará automáticamente para reconexión futura

**Web**: La configuración de Bluetooth no está disponible en Web. La impresión se simula mediante modales de previsualización.

## Uso

### Pantalla Principal POS

La pantalla principal tiene tres secciones:

1. **Izquierda**: Categorías de productos
2. **Centro**: Grid de productos con imágenes y precios
3. **Derecha**: Panel del pedido actual (carrito)

### Crear una Orden

1. Selecciona productos tocando las tarjetas
2. Los productos se agregan al carrito automáticamente
3. Selecciona un cliente (opcional) tocando "Seleccionar Cliente"
4. Ajusta cantidades usando los botones +/-
5. Agrega notas a los items si es necesario

### Enviar a Cocina

1. Presiona **Enviar a Cocina**
2. Se imprimirá automáticamente una comanda de cocina
3. La orden se guardará como "Abierta"

### Órdenes Abiertas

1. Presiona el icono de **Recibos** en la barra superior
2. Verás todas las órdenes abiertas
3. Selecciona una orden para:
   - Agregar nuevos items
   - Actualizar comanda (imprimir solo items nuevos)
   - Imprimir pre-cuenta

### Imprimir Pre-cuenta

1. Con items en el carrito, presiona **Imprimir Pre-cuenta**
2. Se imprimirá un ticket con:
   - Lista de items
   - Subtotal
   - Total en USD
   - Total en Bs (según tasa de cambio)

### Cobrar una Orden

1. Presiona **Cobrar**
2. Selecciona el método de pago:
   - Efectivo
   - Tarjeta
   - Transferencia
3. Se imprimirá el ticket final
4. La orden se marcará como "Cerrada"
5. Si está offline, se sincronizará automáticamente cuando haya conexión

## Sincronización Offline

La aplicación funciona completamente offline:

- **Productos**: Se cargan y guardan localmente
- **Clientes**: Se pueden crear offline y sincronizar después
- **Órdenes**: Se guardan localmente con UUIDs
- **Sincronización**: Las órdenes cerradas se envían al servidor cuando hay conexión

### Sincronización Manual

1. Ve a **Configuración**
2. Presiona **Sincronizar Todo**
3. La aplicación:
   - Descargará productos actualizados
   - Descargará clientes actualizados
   - Enviará órdenes offline pendientes

## Estructura del Proyecto

```
lib/
├── main.dart              # Punto de entrada
├── models/               # Modelos de datos (Hive)
│   ├── producto.dart
│   ├── cliente.dart
│   ├── orden.dart
│   ├── detalle_orden.dart
│   └── configuracion.dart
├── services/             # Servicios
│   ├── api_service.dart      # Comunicación con Laravel
│   ├── sync_service.dart    # Sincronización offline
│   └── bluetooth_service.dart # Impresión ESC/POS
├── providers/            # State Management (Provider)
│   ├── product_provider.dart
│   ├── cart_provider.dart
│   ├── order_provider.dart
│   ├── config_provider.dart
│   └── bluetooth_provider.dart
├── screens/              # Pantallas
│   ├── pos_screen.dart
│   ├── client_selector_screen.dart
│   ├── open_orders_screen.dart
│   └── config_screen.dart
└── widgets/              # Componentes UI
    ├── product_card.dart
    ├── category_sidebar.dart
    └── cart_panel.dart
```

## API Laravel

La aplicación se comunica con la API Laravel en:

- `GET /api/pos/productos` - Obtener productos
- `GET /api/clientes` - Obtener clientes
- `POST /api/clientes` - Crear cliente
- `POST /api/sync/ordenes` - Sincronizar órdenes offline
- `GET /api/health` - Verificar conexión

## Solución de Problemas

### La impresora no conecta

**Mobile**:
- Verifica que Bluetooth esté activado
- Asegúrate de tener los permisos de ubicación
- Reinicia el escaneo de dispositivos

**Web**:
- Bluetooth no está disponible en Web
- Usa el modo de simulación de tickets
- Los tickets se muestran en modales emergentes

### Error de sincronización

- Verifica la URL del servidor en configuración
- Asegúrate de que el servidor Laravel esté ejecutándose
- Verifica la conexión a internet

### La app no guarda datos offline

- Verifica que Hive esté inicializado correctamente
- Revisa los permisos de almacenamiento

### Ejecución en Web

- Usa `flutter run -d chrome` para ejecutar en navegador
- Los datos se guardan en IndexedDB (Hive Web)
- La impresión Bluetooth se simula con modales
- La sincronización funciona igual que en mobile

## Desarrollo

### Ejecutar en modo debug

```bash
flutter run
```

### Build para producción

#### Android APK

```bash
flutter build apk --release
```

#### Android App Bundle

```bash
flutter build appbundle --release
```

#### iOS

```bash
flutter build ios --release
```

## Licencia

Este proyecto es parte de SaveCoin POS.

## Soporte

Para problemas o sugerencias, contacte al equipo de desarrollo.
