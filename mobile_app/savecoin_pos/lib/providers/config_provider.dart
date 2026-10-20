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
        await configBox.add(Configuracion());
      }

      // Intentar sincronizar la tasa de cambio desde el servidor
      await _syncService.syncConfiguracion();

      // Recargar después de sincronizar
      var config = configBox.getAt(0)!;

      // La URL guardada en SharedPreferences (la que se elige en el login) es la
      // fuente de verdad. Antes esta pantalla la pisaba con la de Hive, por eso
      // al reiniciar la app volvía a apuntar a localhost.
      final prefs = await SharedPreferences.getInstance();
      final savedUrl = prefs.getString('api_url');
      if (savedUrl != null && savedUrl.isNotEmpty && savedUrl != config.apiUrl) {
        config = config.copyWith(apiUrl: savedUrl);
        await configBox.putAt(0, config);
      }

      _config = config;
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
      _apiService.setBaseUrl(url);

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
