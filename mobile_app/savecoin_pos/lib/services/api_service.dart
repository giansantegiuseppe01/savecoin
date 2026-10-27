import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/producto.dart';
import '../models/cliente.dart';
import '../models/orden.dart';
import '../models/configuracion.dart';
import '../utils/constants.dart';
import '../utils/log.dart';
import 'token_storage.dart';

/// Error de la API con un mensaje que se puede mostrar al usuario.
class ApiException implements Exception {
  final String message;
  final int? statusCode;

  ApiException(this.message, {this.statusCode});

  @override
  String toString() => message;
}

/// Cliente HTTP de la API. Es un único objeto compartido por toda la app, así
/// la URL y el token son los mismos en todos los providers y servicios.
class ApiService {
  static final ApiService _instance = ApiService._internal();

  factory ApiService() => _instance;

  ApiService._internal();

  static const Duration _timeout = Duration(seconds: 15);

  final TokenStorage _tokenStorage = TokenStorage();

  String _baseUrl = kDefaultApiUrl;
  String? _token;
  Future<void>? _loading;

  /// Se invoca cuando el servidor responde 401 con un token guardado
  /// (token vencido o revocado). AuthProvider lo usa para cerrar la sesión.
  void Function()? onUnauthorized;

  Future<void> _loadConfig() async {
    final prefs = await SharedPreferences.getInstance();
    _baseUrl = prefs.getString('api_url') ?? kDefaultApiUrl;
    _token = await _tokenStorage.read();
  }

  /// Carga la URL y el token guardados. Todas las peticiones esperan a esto,
  /// así nunca se usa la API antes de que la configuración esté lista.
  Future<void> ensureLoaded() => _loading ??= _loadConfig();

  /// Vuelve a leer la URL y el token guardados.
  Future<void> reloadConfig() {
    _loading = _loadConfig();
    return _loading!;
  }

  String get baseUrl => _baseUrl;

  bool get hasToken => _token != null && _token!.isNotEmpty;

  /// Cambia la URL del servidor (el llamador es quien la persiste).
  void setBaseUrl(String url) {
    _baseUrl = url;
    _loading ??= Future.value();
  }

  /// Cambia el token en memoria (el llamador es quien lo persiste).
  void setToken(String? token) {
    _token = token;
    _loading ??= Future.value();
  }

  Map<String, String> get _publicHeaders => {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

  Map<String, String> get _headers => {
        ..._publicHeaders,
        if (hasToken) 'Authorization': 'Bearer $_token',
      };

  /// Punto único de salida de todas las peticiones: espera la configuración,
  /// aplica timeout y avisa si el token ya no es válido.
  Future<http.Response> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    bool auth = true,
  }) async {
    await ensureLoaded();

    final uri = Uri.parse('$_baseUrl$path');
    final headers = auth ? _headers : _publicHeaders;
    final encoded = body == null ? null : json.encode(body);

    try {
      final Future<http.Response> request = switch (method) {
        'GET' => http.get(uri, headers: headers),
        'POST' => http.post(uri, headers: headers, body: encoded),
        'PUT' => http.put(uri, headers: headers, body: encoded),
        'DELETE' => http.delete(uri, headers: headers),
        _ => throw ArgumentError('Método HTTP no soportado: $method'),
      };

      final response = await request.timeout(_timeout);

      if (auth && response.statusCode == 401 && hasToken) {
        onUnauthorized?.call();
      }

      return response;
    } on TimeoutException {
      throw ApiException('El servidor no respondió a tiempo');
    } catch (e) {
      logd('Error de red en $method $path: $e');
      throw ApiException('Error de conexión: $e');
    }
  }

  dynamic _decode(http.Response response) => json.decode(response.body);

  /// Extrae el mensaje de error que devuelve Laravel, si lo hay.
  String _errorMessage(http.Response response, String fallback) {
    try {
      final data = _decode(response);
      if (data is Map && data['message'] is String) {
        return '$fallback: ${data['message']}';
      }
    } catch (_) {}
    return '$fallback (${response.statusCode})';
  }

  // Productos
  Future<List<Producto>> getProductos() async {
    final response = await _send('GET', '/pos/productos');

    if (response.statusCode == 200) {
      final List<dynamic> data = _decode(response);
      return data.map((json) => Producto.fromJson(json)).toList();
    }
    throw ApiException(_errorMessage(response, 'Error al cargar productos'),
        statusCode: response.statusCode);
  }

  // Clientes
  Future<List<Cliente>> getClientes() async {
    final response = await _send('GET', '/clientes');

    if (response.statusCode == 200) {
      final List<dynamic> data = _decode(response)['data'] ?? [];
      return data.map((json) => Cliente.fromJson(json)).toList();
    }
    throw ApiException(_errorMessage(response, 'Error al cargar clientes'),
        statusCode: response.statusCode);
  }

  Future<Cliente> createCliente(Cliente cliente) async {
    final response = await _send('POST', '/clientes', body: cliente.toJson());

    if (response.statusCode == 201) {
      return Cliente.fromJson(_decode(response));
    }
    throw ApiException(_errorMessage(response, 'Error al crear cliente'),
        statusCode: response.statusCode);
  }

  // Órdenes
  Future<Map<String, dynamic>> createOrden(Map<String, dynamic> ordenData) async {
    final response = await _send('POST', '/pos/ordenes', body: ordenData);

    if (response.statusCode == 201) {
      return _decode(response);
    }
    throw ApiException(_errorMessage(response, 'Error al crear orden'),
        statusCode: response.statusCode);
  }

  Future<Map<String, dynamic>> getOrdenes() async {
    final response = await _send('GET', '/pos/ordenes');

    if (response.statusCode == 200) {
      return _decode(response);
    }
    throw ApiException(_errorMessage(response, 'Error al obtener órdenes'),
        statusCode: response.statusCode);
  }

  Future<Map<String, dynamic>> updateOrden(String id, Map<String, dynamic> data) async {
    final response = await _send('PUT', '/pos/ordenes/$id', body: data);

    if (response.statusCode == 200) {
      return _decode(response);
    }
    throw ApiException(_errorMessage(response, 'Error al actualizar orden'),
        statusCode: response.statusCode);
  }

  // Sincronización masiva de órdenes offline
  Future<bool> syncOrdenes(List<Orden> ordenes) async {
    final response = await _send(
      'POST',
      '/sync/ordenes',
      body: {'ordenes': ordenes.map((o) => o.toJson()).toList()},
    );

    return response.statusCode == 200;
  }

  // Configuración
  Future<Configuracion> getConfiguracion() async {
    final response = await _send('GET', '/configuracion');

    if (response.statusCode == 200) {
      return Configuracion.fromJson(_decode(response));
    }
    throw ApiException(_errorMessage(response, 'Error al cargar configuración'),
        statusCode: response.statusCode);
  }

  // Verificar conexión
  Future<bool> checkConnection() async {
    try {
      final response = await _send('GET', '/health', auth: false)
          .timeout(const Duration(seconds: 5));

      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  // Alertas de stock
  Future<Map<String, dynamic>> getStockAlerts() async {
    final response = await _send('GET', '/insumos/stock-alerts');

    if (response.statusCode == 200) {
      return _decode(response);
    }
    throw ApiException(_errorMessage(response, 'Error al cargar alertas de stock'),
        statusCode: response.statusCode);
  }

  // Login (no lleva token)
  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await _send(
      'POST',
      '/login',
      body: {'email': email, 'password': password},
      auth: false,
    );

    if (response.statusCode == 200) {
      return _decode(response);
    }
    if (response.statusCode == 401) {
      throw ApiException('Credenciales incorrectas', statusCode: 401);
    }
    if (response.statusCode == 429) {
      throw ApiException('Demasiados intentos. Espere un minuto e intente de nuevo.',
          statusCode: 429);
    }
    throw ApiException(_errorMessage(response, 'Error al iniciar sesión'),
        statusCode: response.statusCode);
  }

  /// Revoca el token en el servidor. Si falla (sin red) no importa: el token
  /// local se borra igualmente.
  Future<void> logout() async {
    try {
      await _send('POST', '/logout');
    } catch (e) {
      logd('No se pudo revocar el token en el servidor: $e');
    }
  }

  // Turnos
  Future<Map<String, dynamic>> getTurnoActivo() async {
    final response = await _send('GET', '/turnos/activo');

    if (response.statusCode == 200) {
      return _decode(response);
    }
    throw ApiException(_errorMessage(response, 'Error al consultar el turno'),
        statusCode: response.statusCode);
  }

  Future<Map<String, dynamic>> abrirTurno() async {
    final response = await _send('POST', '/turnos/abrir');

    // 400 = "Ya tienes un turno abierto": el provider lo trata como válido.
    if (response.statusCode == 200 ||
        response.statusCode == 201 ||
        response.statusCode == 400) {
      return _decode(response);
    }
    throw ApiException(_errorMessage(response, 'Error al abrir turno'),
        statusCode: response.statusCode);
  }

  Future<Map<String, dynamic>> cerrarTurno() async {
    final response = await _send('POST', '/turnos/cerrar');

    if (response.statusCode == 200) {
      return _decode(response);
    }
    throw ApiException(_errorMessage(response, 'Error al cerrar turno'),
        statusCode: response.statusCode);
  }

  // ---- Administración (solo admin) ----

  Future<List<Map<String, dynamic>>> getInsumosAdmin() async {
    final response = await _send('GET', '/admin/insumos');

    if (response.statusCode == 200) {
      return (_decode(response) as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
    }
    throw ApiException(_errorMessage(response, 'Error al cargar el inventario'),
        statusCode: response.statusCode);
  }

  /// Carga (entrada) o descarga (salida) de un insumo. Devuelve el insumo actualizado.
  Future<Map<String, dynamic>> registrarMovimiento({
    required String insumoId,
    required String tipo,
    required double cantidad,
    required String motivo,
  }) async {
    final response = await _send('POST', '/admin/inventario/movimientos', body: {
      'insumo_id': insumoId,
      'tipo_movimiento': tipo,
      'cantidad': cantidad,
      'motivo': motivo,
    });

    if (response.statusCode == 200) {
      return Map<String, dynamic>.from(_decode(response)['data'] as Map);
    }
    throw ApiException(_errorMessage(response, 'No se pudo registrar el movimiento'),
        statusCode: response.statusCode);
  }

  /// Sube la foto de un producto (multipart). Devuelve la URL completa de la imagen.
  Future<String?> subirImagenProducto(String productoId, Uint8List bytes, String nombreArchivo) async {
    await ensureLoaded();

    final request = http.MultipartRequest('POST', Uri.parse('$_baseUrl/admin/productos/$productoId/imagen'))
      ..headers['Accept'] = 'application/json'
      ..files.add(http.MultipartFile.fromBytes('imagen', bytes, filename: nombreArchivo));
    if (hasToken) request.headers['Authorization'] = 'Bearer $_token';

    try {
      final streamed = await request.send().timeout(const Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);

      if (response.statusCode == 401 && hasToken) onUnauthorized?.call();

      if (response.statusCode == 200) {
        return _decode(response)['data']?['imagen_url']?.toString();
      }
      throw ApiException(_errorMessage(response, 'No se pudo subir la imagen'),
          statusCode: response.statusCode);
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw ApiException('El servidor no respondió a tiempo');
    } catch (e) {
      logd('Error subiendo imagen: $e');
      throw ApiException('Error de conexión: $e');
    }
  }

  Future<void> eliminarImagenProducto(String productoId) async {
    final response = await _send('DELETE', '/admin/productos/$productoId/imagen');

    if (response.statusCode != 200) {
      throw ApiException(_errorMessage(response, 'No se pudo quitar la imagen'),
          statusCode: response.statusCode);
    }
  }
}
