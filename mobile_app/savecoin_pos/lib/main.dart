import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';
import 'app_navigator.dart';
import 'models/cliente.dart';
import 'models/configuracion.dart';
import 'models/detalle_orden.dart';
import 'models/orden.dart';
import 'models/producto.dart';
import 'providers/auth_provider.dart';
import 'providers/bluetooth_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/client_provider.dart';
import 'providers/config_provider.dart';
import 'providers/order_provider.dart';
import 'providers/product_provider.dart';
import 'providers/shift_provider.dart';
import 'screens/initial_screen.dart';
import 'screens/login_screen.dart';
import 'screens/pos_screen.dart';
import 'services/api_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await Hive.initFlutter();

  Hive.registerAdapter(ProductoAdapter());
  Hive.registerAdapter(ClienteAdapter());
  Hive.registerAdapter(OrdenAdapter());
  Hive.registerAdapter(DetalleOrdenAdapter());
  Hive.registerAdapter(ConfiguracionAdapter());

  await Hive.openBox<Producto>('productos');
  await Hive.openBox<Cliente>('clientes');
  await Hive.openBox<Orden>('ordenes');
  await Hive.openBox<DetalleOrden>('detalles_orden');
  await Hive.openBox<Configuracion>('configuracion');
  await Hive.openBox('sync_queue');

  if (!kIsWeb && (Platform.isAndroid || Platform.isIOS)) {
    await _requestPermissions();
  }

  runApp(const SaveCoinPOS());
}

/// Solo lo necesario para imprimir por Bluetooth. Ya no se pide el
/// almacenamiento: la app no lee ni escribe archivos del usuario.
Future<void> _requestPermissions() async {
  await [
    Permission.bluetoothScan,
    Permission.bluetoothConnect,
    // Android 11 o anterior exige ubicación para escanear dispositivos BLE
    Permission.locationWhenInUse,
  ].request();
}

class SaveCoinPOS extends StatelessWidget {
  const SaveCoinPOS({super.key});

  @override
  Widget build(BuildContext context) {
    final apiService = ApiService();

    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ProductProvider()),
        ChangeNotifierProvider(create: (_) => CartProvider()),
        ChangeNotifierProvider(create: (_) => OrderProvider()),
        ChangeNotifierProvider(create: (_) => ConfigProvider()),
        ChangeNotifierProvider(create: (_) => BluetoothProvider()),
        ChangeNotifierProvider(create: (_) => ClientProvider()),
        ChangeNotifierProvider(create: (_) => AuthProvider(apiService)),
        ChangeNotifierProvider(create: (_) => ShiftProvider(apiService)),
      ],
      child: MaterialApp(
        title: 'SaveCoin POS',
        debugShowCheckedModeBanner: false,
        navigatorKey: appNavigatorKey,
        theme: ThemeData(
          primarySwatch: Colors.blue,
          useMaterial3: true,
        ),
        home: const AuthGate(),
      ),
    );
  }
}

/// Decide qué pantalla se ve según el estado: cargando → login → inicio (sin
/// turno) → POS. Al ser la raíz de la app, cerrar sesión (o un 401) solo
/// necesita volver a esta pantalla en lugar de reemplazar rutas a mano.
class AuthGate extends StatelessWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final shift = context.watch<ShiftProvider>();

    if (!auth.isLoaded) return const _Splash();

    if (!auth.isAuthenticated) {
      // Sesión cerrada: no deben quedar el turno ni el pedido del usuario anterior
      if (shift.isLoaded || shift.isShiftOpen) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (!context.mounted) return;
          context.read<ShiftProvider>().reset();
          context.read<CartProvider>().clear();
          context.read<OrderProvider>().clearCurrentOrden();
        });
      }
      return const LoginScreen();
    }

    if (!shift.isLoaded) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (context.mounted) context.read<ShiftProvider>().loadStatus();
      });
      return const _Splash();
    }

    return shift.isShiftOpen ? const POSScreen() : const InitialScreen();
  }
}

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}
