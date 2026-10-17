import 'package:flutter/foundation.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/configuracion.dart';
import '../services/api_service.dart';
import '../services/sync_service.dart';

class ConfigProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  final SyncService _syncService = SyncService();
  
  Configuracion? _config;
  bool _isLoading = false;
  String? _error;

  Configuracion? get config => _config;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> loadConfig() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final configBox = Hive.box<Configuracion>('configuracion');
      
      if (configBox.isEmpty) {
        // Crear configuración por defecto
        _config = Configuracion();
        await configBox.add(_config!);
      } else {
        _config = configBox.getAt(0);
      }
      
      // Intentar sincronizar configuración desde el servidor
      await _syncService.syncConfiguracion();
      
      // Recargar después de sincronizar
      _config = configBox.getAt(0);
      
      // Actualizar API service con la configuración
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('api_url', _config!.apiUrl);
      await prefs.setString('api_token', _config!.apiToken);
      _apiService.updateConfig(_config!.apiUrl, _config!.apiToken);
      
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> updateTasaCambio(double nuevaTasa) async {
    if (_config == null) return;

    try {
      final configBox = Hive.box<Configuracion>('configuracion');
      _config = _config!.copyWith(tasaCambio: nuevaTasa);
      await configBox.putAt(0, _config!);
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<void> updateApiUrl(String url) async {
    if (_config == null) return;

    try {
      final configBox = Hive.box<Configuracion>('configuracion');
      _config = _config!.copyWith(apiUrl: url);
      await configBox.putAt(0, _config!);
      
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('api_url', url);
      _apiService.updateConfig(url, _config!.apiToken);
      
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<void> updateApiToken(String token) async {
    if (_config == null) return;

    try {
      final configBox = Hive.box<Configuracion>('configuracion');
      _config = _config!.copyWith(apiToken: token);
      await configBox.putAt(0, _config!);
      
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('api_token', token);
      _apiService.updateConfig(_config!.apiUrl, token);
      
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<void> updatePrinter(String macAddress, String name) async {
    if (_config == null) return;

    try {
      final configBox = Hive.box<Configuracion>('configuracion');
      _config = _config!.copyWith(
        printerMacAddress: macAddress,
        printerName: name,
      );
      await configBox.putAt(0, _config!);
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<void> syncFromServer() async {
    await _syncService.syncConfiguracion();
    await loadConfig();
  }

  Future<bool> checkConnection() async {
    try {
      return await _apiService.checkConnection();
    } catch (e) {
      return false;
    }
  }
}
