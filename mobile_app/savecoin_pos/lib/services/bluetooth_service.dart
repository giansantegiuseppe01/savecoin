import 'dart:async';
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_blue_plus/flutter_blue_plus.dart';
import 'package:esc_pos_utils/esc_pos_utils.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/orden.dart';
import '../models/detalle_orden.dart';
import '../utils/formatters.dart';
import '../utils/log.dart';

/// Conexión con la impresora Bluetooth. Es un único objeto compartido: antes
/// cada pantalla/provider creaba el suyo, así que la impresora conectada en
/// Configuración no existía para las demás y imprimir siempre fallaba.
class BluetoothService {
  static final BluetoothService _instance = BluetoothService._internal();

  factory BluetoothService() => _instance;

  BluetoothService._internal();

  BluetoothDevice? _connectedDevice;
  BluetoothCharacteristic? _printCharacteristic;
  final List<BluetoothDevice> _availableDevices = [];
  bool _isScanning = false;
  bool _isConnected = false;
  int _chunkSize = 20;
  StreamSubscription<BluetoothConnectionState>? _connectionSubscription;

  /// Lo asigna BluetoothProvider para refrescar la UI si la impresora se desconecta.
  void Function()? onConnectionChanged;

  List<BluetoothDevice> get availableDevices => _availableDevices;
  bool get isScanning => _isScanning;
  bool get isConnected => _isConnected;
  BluetoothDevice? get connectedDevice => _connectedDevice;
  
  // Web simulation mode
  bool get isWeb => kIsWeb;

  // Iniciar escaneo de dispositivos Bluetooth
  Future<void> startScan() async {
    if (isWeb) {
      // Web doesn't support Bluetooth scanning
      throw Exception('Bluetooth no disponible en Web. Use el modo de simulación.');
    }

    if (_isScanning) return;

    _isScanning = true;
    _availableDevices.clear();

    try {
      // Solicitar permisos si no se tienen
      if (await FlutterBluePlus.isSupported == false) {
        logd('Bluetooth no soportado');
        return;
      }

      // Iniciar escaneo
      await FlutterBluePlus.startScan(timeout: const Duration(seconds: 10));

      // Escuchar dispositivos encontrados
      var subscription = FlutterBluePlus.scanResults.listen((results) {
        for (ScanResult r in results) {
          if (!_availableDevices.any((d) => d.remoteId == r.device.remoteId)) {
            _availableDevices.add(r.device);
          }
        }
      });

      // Esperar timeout
      await Future.delayed(const Duration(seconds: 10));
      await FlutterBluePlus.stopScan();
      await subscription.cancel();
    } catch (e) {
      logd('Error escaneando Bluetooth: $e');
    } finally {
      _isScanning = false;
    }
  }

  // Conectar a dispositivo
  Future<bool> connectToDevice(BluetoothDevice device) async {
    if (isWeb) {
      throw Exception('Bluetooth no disponible en Web. Use el modo de simulación.');
    }

    try {
      await device.connect();
      _connectedDevice = device;
      _isConnected = true;
      _printCharacteristic = null;

      // Si la impresora se apaga o se aleja, marcarla como desconectada
      await _connectionSubscription?.cancel();
      _connectionSubscription = device.connectionState.listen((state) {
        if (state == BluetoothConnectionState.disconnected && _isConnected) {
          _isConnected = false;
          _printCharacteristic = null;
          onConnectionChanged?.call();
        }
      });

      // Guardar MAC address
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('printer_mac', device.remoteId.toString());
      await prefs.setString('printer_name', device.platformName);

      // Descubrir servicios y buscar la primera característica de escritura
      final services = await device.discoverServices();
      search:
      for (var service in services) {
        for (var characteristic in service.characteristics) {
          if (characteristic.properties.write ||
              characteristic.properties.writeWithoutResponse) {
            _printCharacteristic = characteristic;
            break search; // un simple `break` solo salía del bucle interno
          }
        }
      }

      // BLE limita cada escritura al MTU (por defecto 23 bytes = 20 de datos)
      _chunkSize = (device.mtuNow - 3).clamp(20, 200).toInt();

      if (_printCharacteristic == null) {
        _isConnected = false;
      }
      return _printCharacteristic != null;
    } catch (e) {
      logd('Error conectando a dispositivo: $e');
      _isConnected = false;
      return false;
    }
  }

  // Desconectar
  Future<void> disconnect() async {
    await _connectionSubscription?.cancel();
    _connectionSubscription = null;

    if (_connectedDevice != null) {
      await _connectedDevice!.disconnect();
      _connectedDevice = null;
      _printCharacteristic = null;
      _isConnected = false;
    }
  }

  // Reconectar a dispositivo guardado
  Future<bool> reconnectToSavedDevice() async {
    if (isWeb) {
      return false; // Web doesn't support Bluetooth
    }

    try {
      final prefs = await SharedPreferences.getInstance();
      final macAddress = prefs.getString('printer_mac');
      
      if (macAddress == null) return false;

      await startScan();
      
      final device = _availableDevices.firstWhere(
        (d) => d.remoteId.toString() == macAddress,
        orElse: () => throw Exception('Dispositivo no encontrado'),
      );

      return await connectToDevice(device);
    } catch (e) {
      logd('Error reconectando: $e');
      return false;
    }
  }

  // Imprimir comanda de cocina
  Future<void> printKitchenCommand(Orden orden, List<DetalleOrden> detalles, {bool onlyNew = false}) async {
    if (isWeb) {
      // En Web, lanzar excepción para que la UI muestre el modal
      throw Exception('WEB_SIMULATION:kitchen_command');
    }

    if (!isConnected || _printCharacteristic == null) {
      throw Exception('Impresora no conectada');
    }

    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      final printer = _createPrinter();

      List<int> bytes = [];

      // Detectar si hay modificaciones para el encabezado
      final tieneModificaciones = detalles.any((d) => d.fueModificado);
      final encabezado = tieneModificaciones ? 'COMANDA MODIFICADA' : 'COMANDA DE COCINA';

      bytes += generator.text(encabezado,
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
            height: PosTextSize.size2,
            width: PosTextSize.size2,
          ));

      // Si está modificada, agregar timestamp de modificación
      if (tieneModificaciones) {
        bytes += generator.text('Modificación: ${_formatDate(DateTime.now())}',
            styles: PosStyles(align: PosAlign.center));
      }

      bytes += generator.hr();
      bytes += generator.text('Orden: ${orden.numeroRecibo}',
          styles: PosStyles(bold: true));
      bytes += generator.text('Cliente: ${orden.clienteNombre}');
      bytes += generator.text('Fecha: ${_formatDate(orden.fechaHora)}');
      
      // Agregar notas del cliente si existen
      if (orden.notas != null && orden.notas!.isNotEmpty) {
        bytes += generator.hr();
        bytes += generator.text('NOTAS DEL CLIENTE:',
            styles: PosStyles(bold: true));
        bytes += generator.text(orden.notas!,
            styles: PosStyles(align: PosAlign.left));
        bytes += generator.hr();
      }
      
      bytes += generator.hr();
      bytes += generator.text('ITEMS:',
          styles: PosStyles(bold: true));
      bytes += generator.hr();

      // Filtrar solo items nuevos si se solicita
      final itemsParaImprimir = onlyNew
          ? detalles.where((d) => d.isNew).toList()
          : detalles;

      for (var detalle in itemsParaImprimir) {
        // Marcar items modificados con asterisco
        final prefijo = detalle.fueModificado ? '* ' : '';
        bytes += generator.text(
            '$prefijo${formatCantidad(detalle.cantidad)}x ${detalle.productoNombre}',
            styles: PosStyles(bold: true));
        if (detalle.notas != null && detalle.notas!.isNotEmpty) {
          bytes += generator.text('Nota: ${detalle.notas}',
              styles: PosStyles(align: PosAlign.left));
        }
      }

      bytes += generator.hr();
      bytes += generator.text(
          onlyNew ? '--- ITEMS NUEVOS ---' : '--- FIN COMANDA ---',
          styles: PosStyles(align: PosAlign.center));
      bytes += generator.feed(2);
      bytes += generator.cut();

      await printer.printBytes(Uint8List.fromList(bytes));
    } catch (e) {
      throw Exception('Error al imprimir comanda: $e');
    }
  }

  // Imprimir pre-cuenta
  Future<void> printPreCuenta(Orden orden, List<DetalleOrden> detalles, double tasaCambio) async {
    if (isWeb) {
      // En Web, lanzar excepción para que la UI muestre el modal
      throw Exception('WEB_SIMULATION:pre_cuenta');
    }

    if (!isConnected || _printCharacteristic == null) {
      throw Exception('Impresora no conectada');
    }

    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      final printer = _createPrinter();

      List<int> bytes = [];

      bytes += generator.text('PRE-CUENTA',
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
            height: PosTextSize.size2,
            width: PosTextSize.size2,
          ));
      bytes += generator.hr();
      bytes += generator.text('Orden: ${orden.numeroRecibo}',
          styles: PosStyles(bold: true));
      bytes += generator.text('Cliente: ${orden.clienteNombre}');
      bytes += generator.text('Fecha: ${_formatDate(orden.fechaHora)}');
      bytes += generator.hr();

      for (var detalle in detalles) {
        bytes += generator.text(detalle.productoNombre);
        bytes += generator.text(
            '${formatCantidad(detalle.cantidad)} x \$${detalle.precioUnitario.toStringAsFixed(2)} = \$${detalle.subtotal.toStringAsFixed(2)}',
            styles: PosStyles(align: PosAlign.right));
      }

      bytes += generator.hr();
      bytes += generator.text('Subtotal: \$${orden.subtotal.toStringAsFixed(2)}',
          styles: PosStyles(align: PosAlign.right));
      bytes += generator.text('Total USD: \$${orden.total.toStringAsFixed(2)}',
          styles: PosStyles(
            align: PosAlign.right,
            bold: true,
            height: PosTextSize.size2,
          ));
      bytes += generator.text('Total Bs: ${orden.totalEnBs.toStringAsFixed(2)}',
          styles: PosStyles(
            align: PosAlign.right,
            bold: true,
            height: PosTextSize.size2,
          ));
      bytes += generator.hr();
      bytes += generator.text('Tasa: \$1 = Bs${tasaCambio.toStringAsFixed(2)}',
          styles: PosStyles(align: PosAlign.center));
      bytes += generator.feed(2);
      bytes += generator.cut();

      await printer.printBytes(Uint8List.fromList(bytes));
    } catch (e) {
      logd('Error imprimiendo pre-cuenta: $e');
      throw Exception('Error al imprimir: $e');
    }
  }

  // PASO 5B: Imprimir ticket de productos añadidos
  Future<void> printProductosAnadidos(Orden orden, List<DetalleOrden> detalles) async {
    if (isWeb) {
      throw Exception('WEB_SIMULATION:productos_anadidos');
    }

    if (!isConnected || _printCharacteristic == null) {
      throw Exception('Impresora no conectada');
    }

    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      final printer = _createPrinter();

      List<int> bytes = [];

      bytes += generator.text('PRODUCTOS AÑADIDOS',
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
            height: PosTextSize.size2,
            width: PosTextSize.size2,
          ));
      bytes += generator.hr();
      bytes += generator.text('Orden: ${orden.numeroRecibo}',
          styles: PosStyles(bold: true));
      bytes += generator.text('Cliente: ${orden.clienteNombre}');
      bytes += generator.text('Fecha: ${_formatDate(DateTime.now())}');
      bytes += generator.hr();

      for (var detalle in detalles) {
        bytes += generator.text(detalle.productoNombre);
        bytes += generator.text(
            '${formatCantidad(detalle.cantidad)} x \$${detalle.precioUnitario.toStringAsFixed(2)}',
            styles: PosStyles(align: PosAlign.right));
        if (detalle.notas != null && detalle.notas!.isNotEmpty) {
          bytes += generator.text('Nota: ${detalle.notas}',
              styles: PosStyles(align: PosAlign.left));
        }
      }

      bytes += generator.hr();
      bytes += generator.feed(2);
      bytes += generator.cut();

      await printer.printBytes(Uint8List.fromList(bytes));
    } catch (e) {
      logd('Error imprimiendo productos añadidos: $e');
      throw Exception('Error al imprimir: $e');
    }
  }

  // PASO 5B: Imprimir ticket de productos eliminados
  Future<void> printProductosEliminados(Orden orden, List<DetalleOrden> detalles) async {
    if (isWeb) {
      throw Exception('WEB_SIMULATION:productos_eliminados');
    }

    if (!isConnected || _printCharacteristic == null) {
      throw Exception('Impresora no conectada');
    }

    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      final printer = _createPrinter();

      List<int> bytes = [];

      bytes += generator.text('PRODUCTOS ELIMINADOS',
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
            height: PosTextSize.size2,
            width: PosTextSize.size2,
          ));
      bytes += generator.hr();
      bytes += generator.text('Orden: ${orden.numeroRecibo}',
          styles: PosStyles(bold: true));
      bytes += generator.text('Cliente: ${orden.clienteNombre}');
      bytes += generator.text('Fecha: ${_formatDate(DateTime.now())}');
      bytes += generator.hr();

      for (var detalle in detalles) {
        bytes += generator.text(detalle.productoNombre);
        bytes += generator.text(
            '${formatCantidad(detalle.cantidad)} x \$${detalle.precioUnitario.toStringAsFixed(2)}',
            styles: PosStyles(align: PosAlign.right));
      }

      bytes += generator.hr();
      bytes += generator.feed(2);
      bytes += generator.cut();

      await printer.printBytes(Uint8List.fromList(bytes));
    } catch (e) {
      logd('Error imprimiendo productos eliminados: $e');
      throw Exception('Error al imprimir: $e');
    }
  }

  // Imprimir ticket final
  Future<void> printFinalTicket(Orden orden, List<DetalleOrden> detalles, double tasaCambio, String metodoPago) async {
    if (isWeb) {
      // En Web, lanzar excepción para que la UI muestre el modal
      throw Exception('WEB_SIMULATION:final_ticket:$metodoPago');
    }

    if (!isConnected || _printCharacteristic == null) {
      throw Exception('Impresora no conectada');
    }

    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      final printer = _createPrinter();

      List<int> bytes = [];

      bytes += generator.text('SAVECOIN POS',
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
            height: PosTextSize.size2,
            width: PosTextSize.size2,
          ));
      bytes += generator.hr();
      bytes += generator.text('RECIBO #${orden.numeroRecibo}',
          styles: PosStyles(bold: true));
      bytes += generator.text('Cliente: ${orden.clienteNombre}');
      bytes += generator.text('Fecha: ${_formatDate(orden.fechaHora)}');
      bytes += generator.hr();

      for (var detalle in detalles) {
        bytes += generator.text(detalle.productoNombre);
        bytes += generator.text(
            '${formatCantidad(detalle.cantidad)} x \$${detalle.precioUnitario.toStringAsFixed(2)} = \$${detalle.subtotal.toStringAsFixed(2)}',
            styles: PosStyles(align: PosAlign.right));
      }

      bytes += generator.hr();
      bytes += generator.text('Subtotal: \$${orden.subtotal.toStringAsFixed(2)}',
          styles: PosStyles(align: PosAlign.right));
      bytes += generator.text('Total USD: \$${orden.total.toStringAsFixed(2)}',
          styles: PosStyles(
            align: PosAlign.right,
            bold: true,
            height: PosTextSize.size2,
          ));
      bytes += generator.text('Total Bs: ${orden.totalEnBs.toStringAsFixed(2)}',
          styles: PosStyles(
            align: PosAlign.right,
            bold: true,
            height: PosTextSize.size2,
          ));
      bytes += generator.hr();
      bytes += generator.text('Método de Pago: $metodoPago',
          styles: PosStyles(align: PosAlign.center));
      bytes += generator.text('Tasa: \$1 = Bs${tasaCambio.toStringAsFixed(2)}',
          styles: PosStyles(align: PosAlign.center));
      bytes += generator.hr();
      bytes += generator.text('¡Gracias por su compra!',
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
          ));
      bytes += generator.feed(2);
      bytes += generator.cut();

      await printer.printBytes(Uint8List.fromList(bytes));
    } catch (e) {
      logd('Error imprimiendo ticket: $e');
      throw Exception('Error al imprimir: $e');
    }
  }

  // Reimprimir comprobante (con etiqueta de COPIA/REIMPRESIÓN)
  Future<void> reprintTicket(Orden orden, List<DetalleOrden> detalles, double tasaCambio, String metodoPago) async {
    if (isWeb) {
      // En Web, lanzar excepción para que la UI muestre el modal
      throw Exception('WEB_SIMULATION:reprint:$metodoPago');
    }

    if (!isConnected || _printCharacteristic == null) {
      throw Exception('Impresora no conectada');
    }

    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      final printer = _createPrinter();

      List<int> bytes = [];

      bytes += generator.text('** COPIA / REIMPRESIÓN **',
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
            height: PosTextSize.size2,
            width: PosTextSize.size2,
          ));
      bytes += generator.hr();
      bytes += generator.text('SAVECOIN POS',
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
          ));
      bytes += generator.hr();
      bytes += generator.text('RECIBO #${orden.numeroRecibo}',
          styles: PosStyles(bold: true));
      bytes += generator.text('Cliente: ${orden.clienteNombre}');
      bytes += generator.text('Fecha: ${_formatDate(orden.fechaHora)}');
      bytes += generator.hr();

      for (var detalle in detalles) {
        bytes += generator.text(detalle.productoNombre);
        bytes += generator.text(
            '${formatCantidad(detalle.cantidad)} x \$${detalle.precioUnitario.toStringAsFixed(2)} = \$${detalle.subtotal.toStringAsFixed(2)}',
            styles: PosStyles(align: PosAlign.right));
      }

      bytes += generator.hr();
      bytes += generator.text('Subtotal: \$${orden.subtotal.toStringAsFixed(2)}',
          styles: PosStyles(align: PosAlign.right));
      bytes += generator.text('Total USD: \$${orden.total.toStringAsFixed(2)}',
          styles: PosStyles(
            align: PosAlign.right,
            bold: true,
            height: PosTextSize.size2,
          ));
      bytes += generator.text('Total Bs: ${orden.totalEnBs.toStringAsFixed(2)}',
          styles: PosStyles(
            align: PosAlign.right,
            bold: true,
            height: PosTextSize.size2,
          ));
      bytes += generator.hr();
      bytes += generator.text('Método de Pago: $metodoPago',
          styles: PosStyles(align: PosAlign.center));
      bytes += generator.text('Tasa: \$1 = Bs${tasaCambio.toStringAsFixed(2)}',
          styles: PosStyles(align: PosAlign.center));
      bytes += generator.hr();
      bytes += generator.text('¡Gracias por su compra!',
          styles: PosStyles(
            align: PosAlign.center,
            bold: true,
          ));
      bytes += generator.feed(2);
      bytes += generator.cut();

      await printer.printBytes(Uint8List.fromList(bytes));
    } catch (e) {
      logd('Error reimprimiendo ticket: $e');
      throw Exception('Error al reimprimir: $e');
    }
  }

  String _formatDate(DateTime date) => formatFechaHora(date);

  BluetoothPrinter _createPrinter() =>
      BluetoothPrinter(_printCharacteristic!, chunkSize: _chunkSize);

  void dispose() {
    disconnect();
  }
}

// Clase auxiliar para impresión Bluetooth
class BluetoothPrinter {
  final BluetoothCharacteristic characteristic;
  final int chunkSize;

  BluetoothPrinter(this.characteristic, {this.chunkSize = 20});

  /// Envía los bytes en trozos que quepan en el MTU. Un ticket completo mide
  /// cientos de bytes y una sola escritura más grande que el MTU falla o se corta.
  Future<void> printBytes(Uint8List bytes) async {
    final withoutResponse = characteristic.properties.writeWithoutResponse;

    for (var i = 0; i < bytes.length; i += chunkSize) {
      final end = (i + chunkSize < bytes.length) ? i + chunkSize : bytes.length;
      await characteristic.write(bytes.sublist(i, end), withoutResponse: withoutResponse);

      if (withoutResponse) {
        // Dar tiempo a la impresora a vaciar su buffer
        await Future.delayed(const Duration(milliseconds: 20));
      }
    }
  }
}
