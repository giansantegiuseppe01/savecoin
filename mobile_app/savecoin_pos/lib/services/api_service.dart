import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/producto.dart';
import '../models/cliente.dart';
import '../models/orden.dart';
import '../models/configuracion.dart';

class ApiService {
  late String _baseUrl;
  String? _token;

  ApiService() {
    _loadConfig();
  }

  Future<void> _loadConfig() async {
    final prefs = await SharedPreferences.getInstance();
    // Usa la IP real de tu máquina en la red local en lugar de localhost
    _baseUrl = prefs.getString('api_url') ?? 'http://localhost:8000/api';
    _token = prefs.getString('auth_token'); // Usar auth_token en lugar de api_token
  }

  String get baseUrl => _baseUrl;

  void updateConfig(String baseUrl, String? token) {
    _baseUrl = baseUrl;
    _token = token;
  }

  Map<String, String> get _headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    if (_token != null) 'Authorization': 'Bearer $_token',
  };

  // Productos
  Future<List<Producto>> getProductos() async {
    try {
      final response = await http.get(
        Uri.parse('$_baseUrl/pos/productos'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = json.decode(response.body);
        return data.map((json) => Producto.fromJson(json)).toList();
      }
      throw Exception('Error al cargar productos');
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }

  Future<Producto> createProducto(Producto producto) async {
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/productos'),
        headers: _headers,
        body: json.encode(producto.toJson()),
      );

      if (response.statusCode == 201) {
        return Producto.fromJson(json.decode(response.body));
      }
      throw Exception('Error al crear producto');
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }

  // Clientes
  Future<List<Cliente>> getClientes() async {
    try {
      final response = await http.get(
        Uri.parse('$_baseUrl/clientes'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = json.decode(response.body)['data'] ?? [];
        return data.map((json) => Cliente.fromJson(json)).toList();
      }
      throw Exception('Error al cargar clientes');
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }

  Future<Cliente> createCliente(Cliente cliente) async {
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/clientes'),
        headers: _headers,
        body: json.encode(cliente.toJson()),
      );

      if (response.statusCode == 201) {
        return Cliente.fromJson(json.decode(response.body));
      }
      throw Exception('Error al crear cliente');
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }

  // Órdenes
  Future<Map<String, dynamic>> createOrden(Map<String, dynamic> ordenData) async {
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/pos/ordenes'),
        headers: _headers,
        body: json.encode(ordenData),
      );

      print('createOrden response: ${response.statusCode}');
      print('createOrden body: ${response.body}');

      if (response.statusCode == 201) {
        return json.decode(response.body);
      }
      throw Exception('Error al crear orden');
    } catch (e) {
      print('Error en createOrden: $e');
      throw Exception('Error de conexión: $e');
    }
  }

  Future<Map<String, dynamic>> getOrdenes() async {
    try {
      final response = await http.get(
        Uri.parse('$_baseUrl/pos/ordenes'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      throw Exception('Error al obtener órdenes');
    } catch (e) {
      print('Error en getOrdenes: $e');
      throw Exception('Error de conexión: $e');
    }
  }

  Future<Map<String, dynamic>> updateOrden(String id, Orden orden) async {
    try {
      final url = '$_baseUrl/pos/ordenes/$id';
      print('updateOrden llamada a: $url');
      print('Token: $_token');
      print('Payload: ${orden.toJson()}');

      final response = await http.put(
        Uri.parse(url),
        headers: _headers,
        body: json.encode(orden.toJson()),
      );

      print('Response status: ${response.statusCode}');
      print('Response body: ${response.body}');

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      throw Exception('Error al actualizar orden');
    } catch (e) {
      print('Error en updateOrden: $e');
      throw Exception('Error de conexión: $e');
    }
  }

  // Sincronización masiva de órdenes offline
  Future<bool> syncOrdenes(List<Orden> ordenes) async {
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/sync/ordenes'),
        headers: _headers,
        body: json.encode({
          'ordenes': ordenes.map((o) => o.toJson()).toList(),
        }),
      );

      return response.statusCode == 200;
    } catch (e) {
      throw Exception('Error de sincronización: $e');
    }
  }

  // Configuración
  Future<Configuracion> getConfiguracion() async {
    try {
      final response = await http.get(
        Uri.parse('$_baseUrl/configuracion'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        return Configuracion.fromJson(json.decode(response.body));
      }
      throw Exception('Error al cargar configuración');
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }

  // Verificar conexión
  Future<bool> checkConnection() async {
    try {
      final response = await http.get(
        Uri.parse('$_baseUrl/health'),
        headers: _headers,
      ).timeout(const Duration(seconds: 5));

      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  // Alertas de stock
  Future<Map<String, dynamic>> getStockAlerts() async {
    try {
      final response = await http.get(
        Uri.parse('$_baseUrl/insumos/stock-alerts'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      throw Exception('Error al cargar alertas de stock');
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }

  // Login
  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/login'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: json.encode({
          'email': email,
          'password': password,
        }),
      );

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      throw Exception('Error al iniciar sesión');
    } catch (e) {
      throw Exception('Error de conexión: $e');
    }
  }

  // Abrir turno
  Future<Map<String, dynamic>> abrirTurno() async {
    try {
      print('abrirTurno llamada a: $_baseUrl/turnos/abrir');
      print('Token: $_token');

      final response = await http.post(
        Uri.parse('$_baseUrl/turnos/abrir'),
        headers: _headers,
      );

      print('Response status: ${response.statusCode}');
      print('Response body: ${response.body}');

      if (response.statusCode == 200 || response.statusCode == 201 || response.statusCode == 400) {
        return json.decode(response.body);
      }
      throw Exception('Error al abrir turno');
    } catch (e) {
      print('Error en abrirTurno: $e');
      throw Exception('Error de conexión: $e');
    }
  }

  // Cerrar turno
  Future<Map<String, dynamic>> cerrarTurno() async {
    try {
      print('cerrarTurno llamada a: $_baseUrl/turnos/cerrar');
      print('Token: $_token');

      final response = await http.post(
        Uri.parse('$_baseUrl/turnos/cerrar'),
        headers: _headers,
      );

      print('Response status: ${response.statusCode}');
      print('Response body: ${response.body}');

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      throw Exception('Error al cerrar turno');
    } catch (e) {
      print('Error en cerrarTurno: $e');
      throw Exception('Error de conexión: $e');
    }
  }
}
