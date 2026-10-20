import 'package:flutter/foundation.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../models/cliente.dart';
import '../services/api_service.dart';
import '../services/sync_service.dart';
import '../utils/log.dart';

class ClientProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  final SyncService _syncService = SyncService();
  
  List<Cliente> _clientes = [];
  List<Cliente> _filteredClientes = [];
  String? _searchQuery;
  bool _isLoading = false;
  String? _error;

  List<Cliente> get clientes => _filteredClientes;
  String? get searchQuery => _searchQuery;
  bool get isLoading => _isLoading;
  String? get error => _error;

  // Cargar clientes desde Hive
  Future<void> loadClientes() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final clientesBox = Hive.box<Cliente>('clientes');
      
      // Cargar desde Hive primero (offline)
      _clientes = clientesBox.values.toList();
      _applyFilter();
      
      // Intentar sincronizar si hay conexión
      await _syncService.syncClientes();
      
      // Recargar después de sincronizar
      _clientes = clientesBox.values.toList();
      _applyFilter();
      
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
    }
  }

  // Buscar clientes
  void searchClientes(String query) {
    _searchQuery = query;
    _applyFilter();
    notifyListeners();
  }

  void _applyFilter() {
    if (_searchQuery == null || _searchQuery!.isEmpty) {
      _filteredClientes = _clientes;
    } else {
      final q = _searchQuery!.toLowerCase();
      _filteredClientes = _clientes.where((c) {
        return c.nombreCompleto.toLowerCase().contains(q) ||
               c.cedula.toLowerCase().contains(q) ||
               (c.telefono?.toLowerCase().contains(q) ?? false);
      }).toList();
    }
  }

  // Crear nuevo cliente con estrategia Online First con fallback a Hive.
  // Devuelve el cliente tal como quedó guardado (con el ID del servidor si
  // hubo conexión) o null si falló. Usar ESE objeto en la orden: el original
  // tiene un ID temporal que el servidor no conoce.
  Future<Cliente?> createCliente(Cliente cliente) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final clientesBox = Hive.box<Cliente>('clientes');

      // Intentar sincronizar con API primero (Online First)
      Cliente clienteGuardado;

      if (await _syncService.hasConnection) {
        try {
          final clienteDesdeAPI = await _apiService.createCliente(cliente);
          clienteGuardado = clienteDesdeAPI.copyWith(isLocal: false, syncedAt: DateTime.now());
        } catch (e) {
          logd('Error al crear cliente en API, haciendo fallback a Hive: $e');
          clienteGuardado = cliente.copyWith(isLocal: true);
        }
      } else {
        // Sin conexión, guardar localmente con isLocal = true
        clienteGuardado = cliente.copyWith(isLocal: true);
      }

      await clientesBox.put(clienteGuardado.id, clienteGuardado);

      _clientes = clientesBox.values.toList();
      _applyFilter();

      _isLoading = false;
      notifyListeners();
      return clienteGuardado;
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
      return null;
    }
  }

  // Sincronizar clientes manualmente
  Future<void> syncClientes() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    
    try {
      // Verificar conexión primero
      if (!await _syncService.hasConnection) {
        _error = 'Sin conexión a internet';
        _isLoading = false;
        notifyListeners();
        return;
      }
      
      await _syncService.syncClientes();
      await loadClientes();
    } catch (e) {
      _error = 'Error de sincronización: $e';
      _isLoading = false;
      notifyListeners();
    }
  }

  // Refrescar lista
  Future<void> refresh() async {
    await loadClientes();
  }

  /// Vuelve a leer los clientes desde Hive, sin llamar al servidor.
  void reloadLocal() {
    _clientes = Hive.box<Cliente>('clientes').values.toList();
    _applyFilter();
    notifyListeners();
  }
}
