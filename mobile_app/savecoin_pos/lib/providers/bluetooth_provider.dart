import 'package:flutter/foundation.dart';
import 'package:flutter_blue_plus/flutter_blue_plus.dart';
import '../services/bluetooth_service.dart' as local;

class BluetoothProvider with ChangeNotifier {
  final local.BluetoothService _bluetoothService = local.BluetoothService();
  
  List<BluetoothDevice> _devices = [];
  bool _isScanning = false;
  bool _isConnected = false;
  BluetoothDevice? _connectedDevice;
  String? _error;

  List<BluetoothDevice> get devices => _devices;
  bool get isScanning => _isScanning;
  bool get isConnected => _isConnected;
  BluetoothDevice? get connectedDevice => _connectedDevice;
  String? get error => _error;

  Future<void> startScan() async {
    _isScanning = true;
    _error = null;
    notifyListeners();

    try {
      await _bluetoothService.startScan();
      _devices = _bluetoothService.availableDevices;
      _isScanning = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      _isScanning = false;
      notifyListeners();
    }
  }

  Future<bool> connectToDevice(BluetoothDevice device) async {
    _error = null;
    notifyListeners();

    try {
      final success = await _bluetoothService.connectToDevice(device);
      if (success) {
        _connectedDevice = device;
        _isConnected = true;
        notifyListeners();
        return true;
      }
      _error = 'No se pudo conectar a la impresora';
      notifyListeners();
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<void> disconnect() async {
    try {
      await _bluetoothService.disconnect();
      _connectedDevice = null;
      _isConnected = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<bool> reconnectToSavedDevice() async {
    _error = null;
    notifyListeners();

    try {
      final success = await _bluetoothService.reconnectToSavedDevice();
      if (success) {
        _connectedDevice = _bluetoothService.connectedDevice;
        _isConnected = true;
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  local.BluetoothService get service => _bluetoothService;

  @override
  void dispose() {
    _bluetoothService.dispose();
    super.dispose();
  }
}
