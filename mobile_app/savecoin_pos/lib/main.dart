import 'dart:io';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:provider/provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'models/producto.dart';
import 'models/cliente.dart';
import 'models/orden.dart';
import 'models/detalle_orden.dart';
import 'models/configuracion.dart';
import 'providers/product_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/order_provider.dart';
import 'providers/config_provider.dart';
import 'providers/bluetooth_provider.dart';
import 'providers/client_provider.dart';
import 'providers/auth_provider.dart';
import 'providers/shift_provider.dart';
import 'services/api_service.dart';
import 'screens/login_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Hive
  await Hive.initFlutter();

  // Register Hive Adapters
  Hive.registerAdapter(ProductoAdapter());
  Hive.registerAdapter(ClienteAdapter());
  Hive.registerAdapter(OrdenAdapter());
  Hive.registerAdapter(DetalleOrdenAdapter());
  Hive.registerAdapter(ConfiguracionAdapter());

  // Open Hive Boxes
  await Hive.openBox<Producto>('productos');
  await Hive.openBox<Cliente>('clientes');
  await Hive.openBox<Orden>('ordenes');
  await Hive.openBox<DetalleOrden>('detalles_orden');
  await Hive.openBox<Configuracion>('configuracion');
  await Hive.openBox('sync_queue');

  // Request permissions only on mobile platforms
  if (!kIsWeb && (Platform.isAndroid || Platform.isIOS)) {
    await _requestPermissions();
  }

  runApp(const SaveCoinPOS());
}

Future<void> _requestPermissions() async {
  await [
    Permission.bluetooth,
    Permission.bluetoothScan,
    Permission.bluetoothConnect,
    Permission.location,
    Permission.storage,
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
        theme: ThemeData(
          primarySwatch: Colors.blue,
          useMaterial3: true,
        ),
        home: const LoginScreen(),
      ),
    );
  }
}
