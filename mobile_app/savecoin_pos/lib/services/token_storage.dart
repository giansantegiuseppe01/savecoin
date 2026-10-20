import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Guarda el token de la API en el almacenamiento seguro del sistema
/// (Keystore en Android, Keychain en iOS) en vez de SharedPreferences, que es
/// texto plano. Si el almacenamiento seguro no está disponible (algunos
/// navegadores), recurre a SharedPreferences.
class TokenStorage {
  static const _key = 'auth_token';
  static const FlutterSecureStorage _secure = FlutterSecureStorage();

  Future<String?> read() async {
    try {
      final token = await _secure.read(key: _key);
      if (token != null && token.isNotEmpty) return token;
    } catch (_) {
      // Sin almacenamiento seguro: se usa SharedPreferences más abajo.
    }

    // Token guardado por versiones anteriores de la app (texto plano).
    final prefs = await SharedPreferences.getInstance();
    final legacy = prefs.getString(_key);
    if (legacy == null || legacy.isEmpty) return null;

    await write(legacy); // migra al almacenamiento seguro si es posible
    return legacy;
  }

  Future<void> write(String token) async {
    final prefs = await SharedPreferences.getInstance();
    try {
      await _secure.write(key: _key, value: token);
      await prefs.remove(_key); // ya no queda copia en texto plano
    } catch (_) {
      await prefs.setString(_key, token);
    }
  }

  Future<void> clear() async {
    try {
      await _secure.delete(key: _key);
    } catch (_) {}
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }
}
