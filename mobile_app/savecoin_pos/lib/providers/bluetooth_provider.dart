import 'package:flutter/foundation.dart';
import 'package:flutter_blue_plus/flutter_blue_plus.dart';
import '../services/bluetooth_service.dart' as local;

class BluetoothProvider with ChangeNotifier {
  // Servicio compartido por toda la app: el estado de conexión vive ahí.
  final local.BluetoothService _bluetoothService = local.BluetoothService();

  bool _isScanning = false;
  String? _error;

  BluetoothProvider() {
    // Si la impresora se desconecta (apagada, fuera de alcance), refrescar la UI
    _bluetoothService.onConnectionChanged = notifyListeners;
  }

  List<BluetoothDevice> get devices => _bluetoothService.availableDevices;
  bool get isScanning => _isScanning;
  bool get isConnected => _bluetoothService.isConnected;
  BluetoothDevice? get connectedDevice => _bluetoothService.connectedDevice;
  String? get error => _error;

  Future<void> startScan() async {
    _isScanning = true;
    _error = null;
    notifyListeners();

    try {
      await _bluetoothService.startScan();
    } catch (e) {
      _error = e.toString();
    } finally {
      _isScanning = false;
      notifyListeners();
    }
  }

  Future<bool> connectToDevice(BluetoothDevice device) async {
    _error = null;
    notifyListeners();

    try {
      final success = await _bluetoothService.connectToDevice(device);
      if (!success) {
        _error = 'No se pudo conectar a la impresora';
      }
      notifyListeners();
      return success;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<void> disconnect() async {
    try {
      await _bluetoothService.disconnect();
    } catch (e) {
      _error = e.toString();
    }
    notifyListeners();
  }

  /// Reconecta con la última impresora usada (si hay una guardada).
  Future<bool> reconnectToSavedDevice() async {
    _error = null;

    try {
      final success = await _bluetoothService.reconnectToSavedDevice();
      notifyListeners();
      return success;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  local.BluetoothService get service => _bluetoothService;

  // No se llama a `_bluetoothService.dispose()`: el servicio es compartido y
  // sigue en uso aunque este provider se destruya.
}
