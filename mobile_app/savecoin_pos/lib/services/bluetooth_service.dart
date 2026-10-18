import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_blue_plus/flutter_blue_plus.dart';
import 'package:esc_pos_utils/esc_pos_utils.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/orden.dart';
import '../models/detalle_orden.dart';

class BluetoothService {
  BluetoothDevice? _connectedDevice;
  BluetoothCharacteristic? _printCharacteristic;
  List<BluetoothDevice> _availableDevices = [];
  bool _isScanning = false;
  bool _isConnected = false;

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
        print('Bluetooth no soportado');
        return;
      }

      // Iniciar escaneo
      await FlutterBluePlus.startScan(timeout: const Duration(seconds: 10));

      // Escuchar dispositivos encontrados
      var subscription = FlutterBluePlus.scanResults.listen((results) {
        for (ScanResult r in results) {
          if (!_availableDevices.any((d) => d.id == r.device.id)) {
            _availableDevices.add(r.device);
          }
        }
      });

      // Esperar timeout
      await Future.delayed(const Duration(seconds: 10));
      await FlutterBluePlus.stopScan();
      await subscription.cancel();
    } catch (e) {
      print('Error escaneando Bluetooth: $e');
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

      // Guardar MAC address
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('printer_mac', device.id.toString());
      await prefs.setString('printer_name', device.name);

      // Descubrir servicios y características
      final services = await device.discoverServices();
      for (var service in services) {
        for (var characteristic in service.characteristics) {
          if (characteristic.properties.write ||
              characteristic.properties.writeWithoutResponse) {
            _printCharacteristic = characteristic;
            break;
          }
        }
      }

      return _printCharacteristic != null;
    } catch (e) {
      print('Error conectando a dispositivo: $e');
      _isConnected = false;
      return false;
    }
  }

  // Desconectar
  Future<void> disconnect() async {
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
        (d) => d.id.toString() == macAddress,
        orElse: () => throw Exception('Dispositivo no encontrado'),
      );

      return await connectToDevice(device);
    } catch (e) {
      print('Error reconectando: $e');
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
      final printer = BluetoothPrinter(_printCharacteristic!);

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
            '$prefijo${detalle.cantidad}x ${detalle.productoNombre}',
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
      throw Exception('Error imprimiendo comanda: $e');
      throw Exception('Error al imprimir: $e');
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
      final printer = BluetoothPrinter(_printCharacteristic!);

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
        bytes += generator.text('${detalle.productoNombre}');
        bytes += generator.text(
            '${detalle.cantidad} x \$${detalle.precioUnitario.toStringAsFixed(2)} = \$${detalle.subtotal.toStringAsFixed(2)}',
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
      print('Error imprimiendo pre-cuenta: $e');
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
      final printer = BluetoothPrinter(_printCharacteristic!);

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
        bytes += generator.text('${detalle.productoNombre}');
        bytes += generator.text(
            '${detalle.cantidad} x \$${detalle.precioUnitario.toStringAsFixed(2)}',
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
      print('Error imprimiendo productos añadidos: $e');
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
      final printer = BluetoothPrinter(_printCharacteristic!);

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
        bytes += generator.text('${detalle.productoNombre}');
        bytes += generator.text(
            '${detalle.cantidad} x \$${detalle.precioUnitario.toStringAsFixed(2)}',
            styles: PosStyles(align: PosAlign.right));
      }

      bytes += generator.hr();
      bytes += generator.feed(2);
      bytes += generator.cut();

      await printer.printBytes(Uint8List.fromList(bytes));
    } catch (e) {
      print('Error imprimiendo productos eliminados: $e');
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
      final printer = BluetoothPrinter(_printCharacteristic!);

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
        bytes += generator.text('${detalle.productoNombre}');
        bytes += generator.text(
            '${detalle.cantidad} x \$${detalle.precioUnitario.toStringAsFixed(2)} = \$${detalle.subtotal.toStringAsFixed(2)}',
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
      print('Error imprimiendo ticket: $e');
      throw Exception('Error al imprimir: $e');
    }
  }

  // Imprimir ticket de anulación para cocina/barra
  Future<void> printAnulacionTicket(Orden orden, DetalleOrden detalle, double cantidadEliminada) async {
    if (isWeb) {
      // En Web, lanzar excepción para que la UI muestre el modal
      throw Exception('WEB_SIMULATION:anulacion');
    }

    if (!isConnected || _printCharacteristic == null) {
      throw Exception('Impresora no conectada');
    }

    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      final printer = BluetoothPrinter(_printCharacteristic!);

      List<int> bytes = [];

      bytes += generator.text('*** ANULACIÓN DE COMANDA ***',
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
      bytes += generator.text('PRODUCTO ELIMINADO:',
          styles: PosStyles(bold: true));
      bytes += generator.hr();
      bytes += generator.text(
          '-${cantidadEliminada.toStringAsFixed(0)} ${detalle.productoNombre}',
          styles: PosStyles(bold: true));
      bytes += generator.text('Cantidad anterior: ${detalle.cantidad + cantidadEliminada}');
      bytes += generator.text('Cantidad nueva: ${detalle.cantidad}');
      bytes += generator.hr();
      bytes += generator.text('--- FIN ANULACIÓN ---',
          styles: PosStyles(align: PosAlign.center));
      bytes += generator.feed(2);
      bytes += generator.cut();

      await printer.printBytes(Uint8List.fromList(bytes));
    } catch (e) {
      print('Error imprimiendo ticket de anulación: $e');
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
      final printer = BluetoothPrinter(_printCharacteristic!);

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
        bytes += generator.text('${detalle.productoNombre}');
        bytes += generator.text(
            '${detalle.cantidad} x \$${detalle.precioUnitario.toStringAsFixed(2)} = \$${detalle.subtotal.toStringAsFixed(2)}',
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
      print('Error reimprimiendo ticket: $e');
      throw Exception('Error al reimprimir: $e');
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
  }

  void dispose() {
    disconnect();
  }
}

// Clase auxiliar para impresión Bluetooth
class BluetoothPrinter {
  final BluetoothCharacteristic characteristic;

  BluetoothPrinter(this.characteristic);

  Future<void> printBytes(Uint8List bytes) async {
    await characteristic.write(bytes);
  }
}
