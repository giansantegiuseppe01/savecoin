import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../app_navigator.dart';
import '../services/api_service.dart';
import '../services/token_storage.dart';
import '../utils/log.dart';

class AuthProvider with ChangeNotifier {
  final ApiService _apiService;
  final TokenStorage _tokenStorage = TokenStorage();

  String? _token;
  String? _userId;
  String? _userName;
  String? _rol;
  bool _isAuthenticated = false;
  bool _isLoaded = false;
  String? _error;

  AuthProvider(this._apiService) {
    // Si el servidor rechaza el token (vencido o revocado) se cierra la sesión
    _apiService.onUnauthorized = _handleUnauthorized;
    _loadAuthData();
  }

  String? get token => _token;
  String? get userId => _userId;
  String? get userName => _userName;

  /// Solo los administradores ven la sección de administración (inventario y fotos).
  /// El servidor vuelve a comprobarlo en cada petición.
  bool get isAdmin => _rol == 'admin';
  bool get isAuthenticated => _isAuthenticated;

  /// false mientras se lee la sesión guardada al abrir la app.
  bool get isLoaded => _isLoaded;

  /// Último mensaje de error del login (para mostrarlo en pantalla).
  String? get error => _error;

  Future<void> _loadAuthData() async {
    final prefs = await SharedPreferences.getInstance();
    _token = await _tokenStorage.read();
    _userId = prefs.getString('user_id');
    _userName = prefs.getString('user_name');
    _rol = prefs.getString('user_rol');
    _isAuthenticated = _token != null;

    await _apiService.reloadConfig();
    _isLoaded = true;
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    _error = null;

    try {
      // Recargar la URL por si se cambió en la pantalla de login
      await _apiService.reloadConfig();

      final response = await _apiService.login(email, password);
      if (response['success'] == true) {
        _token = response['token'] as String;
        _userId = response['user']['id'].toString();
        _userName = response['user']['name']?.toString() ?? email;
        _rol = response['user']['rol']?.toString();
        _isAuthenticated = true;

        await _tokenStorage.write(_token!);
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('user_id', _userId!);
        await prefs.setString('user_name', _userName!);
        if (_rol != null) {
          await prefs.setString('user_rol', _rol!);
        } else {
          await prefs.remove('user_rol');
        }

        // El mismo ApiService lo usan todos los providers
        _apiService.setToken(_token);

        notifyListeners();
        return true;
      }

      _error = 'Credenciales incorrectas';
      return false;
    } catch (e) {
      logd('Login error: $e');
      _error = e is ApiException ? e.message : 'No se pudo iniciar sesión';
      return false;
    }
  }

  Future<void> logout() async {
    // Revocar el token en el servidor mientras todavía es válido
    await _apiService.logout();
    await _clearSession();
  }

  Future<void> _handleUnauthorized() async {
    if (!_isAuthenticated) return;
    logd('Sesión vencida: el servidor rechazó el token.');
    _error = 'Su sesión venció. Inicie sesión de nuevo.';
    await _clearSession();
  }

  Future<void> _clearSession() async {
    _token = null;
    _userId = null;
    _userName = null;
    _rol = null;
    _isAuthenticated = false;
    _apiService.setToken(null);

    await _tokenStorage.clear();
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('user_id');
    await prefs.remove('user_name');
    await prefs.remove('user_rol');

    // Volver a la raíz de la app, que muestra el login (ver AuthGate)
    appNavigatorKey.currentState?.popUntil((route) => route.isFirst);

    notifyListeners();
  }
}
