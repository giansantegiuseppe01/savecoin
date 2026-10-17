import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';

class AuthProvider with ChangeNotifier {
  final ApiService _apiService;
  String? _token;
  String? _userId;
  String? _userName;
  bool _isAuthenticated = false;

  AuthProvider(this._apiService) {
    _loadAuthData();
  }

  String? get token => _token;
  String? get userId => _userId;
  String? get userName => _userName;
  bool get isAuthenticated => _isAuthenticated;

  Future<void> _loadAuthData() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('auth_token');
    _userId = prefs.getString('user_id');
    _userName = prefs.getString('user_name');
    _isAuthenticated = _token != null;
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    try {
      // Always call the actual API for both web and mobile
      final response = await _apiService.login(email, password);
      if (response['success'] == true) {
        _token = response['token'];
        _userId = response['user']['id'].toString();
        _userName = response['user']['name'];
        _isAuthenticated = true;

        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', _token!);
        await prefs.setString('user_id', _userId!);
        await prefs.setString('user_name', _userName!);

        // Actualizar el token en ApiService
        _apiService.updateConfig(_apiService.baseUrl, _token);

        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      print('Login error: $e');
      return false;
    }
  }

  Future<void> logout() async {
    _token = null;
    _userId = null;
    _userName = null;
    _isAuthenticated = false;

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await prefs.remove('user_id');
    await prefs.remove('user_name');

    notifyListeners();
  }
}
