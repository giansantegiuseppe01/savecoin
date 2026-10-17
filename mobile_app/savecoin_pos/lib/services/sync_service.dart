import 'package:hive_flutter/hive_flutter.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'api_service.dart';
import '../models/producto.dart';
import '../models/cliente.dart';
import '../models/orden.dart';
import '../models/configuracion.dart';

class SyncService {
  final ApiService _apiService = ApiService();
  final Connectivity _connectivity = Connectivity();
  bool _isSyncing = false;

  bool get isSyncing => _isSyncing;

  // Verificar si hay conexión a internet/red
  Future<bool> get hasConnection async {
    try {
      final dynamic result = await _connectivity.checkConnectivity();
      if (result is List) {
        return !result.contains(ConnectivityResult.none);
      }
      return result != ConnectivityResult.none;
    } catch (_) {
      return true; // En Web, en caso de duda, permite intentar el fetch HTTP
    }
  }

  // Sincronizar productos desde el servidor
  Future<void> syncProductos() async {
    if (!await hasConnection) return;

    try {
      final productos = await _apiService.getProductos();
      final productosBox = Hive.box<Producto>('productos');
      
      // Limpiar catálogo previo e insertar lo actualizado por el servidor
      await productosBox.clear();
      
      for (var producto in productos) {
        await productosBox.put(producto.id, producto);
      }
      
      print('Sincronizados ${productos.length} productos en Hive.');
    } catch (e) {
      print('Error sincronizando productos: $e');
    }
  }

  // Sincronizar clientes desde el servidor
  Future<void> syncClientes() async {
    if (!await hasConnection) return;

    try {
      final clientes = await _apiService.getClientes();
      final clientesBox = Hive.box<Cliente>('clientes');
      
      for (var cliente in clientes) {
        await clientesBox.put(cliente.id, cliente);
      }
      
      print('Sincronizados ${clientes.length} clientes desde el servidor');
    } catch (e) {
      print('Error sincronizando clientes: $e');
    }
  }

  // Sincronizar clientes locales al servidor
  Future<void> syncClientesLocales() async {
    if (!await hasConnection || _isSyncing) return;

    _isSyncing = true;

    try {
      final clientesBox = Hive.box<Cliente>('clientes');
      final syncQueueBox = Hive.box('sync_queue');

      final clientesPendientes = clientesBox.values
          .where((c) => c.isLocal)
          .toList();

      if (clientesPendientes.isEmpty) {
        _isSyncing = false;
        return;
      }

      for (var cliente in clientesPendientes) {
        try {
          final clienteDesdeAPI = await _apiService.createCliente(cliente);
          // Eliminar el cliente local con ID temporal
          await clientesBox.delete(cliente.id);
          // Agregar el cliente desde API con ID real
          await clientesBox.put(clienteDesdeAPI.id, clienteDesdeAPI);
          await syncQueueBox.delete(cliente.id);
          print('Cliente sincronizado: ${cliente.nombreCompleto}');
        } catch (e) {
          print('Error sincronizando cliente ${cliente.nombreCompleto}: $e');
        }
      }
    } catch (e) {
      print('Error en sincronización de clientes: $e');
    } finally {
      _isSyncing = false;
    }
  }

  // Sincronizar configuración
  Future<void> syncConfiguracion() async {
    if (!await hasConnection) return;

    try {
      final config = await _apiService.getConfiguracion();
      final configBox = Hive.box<Configuracion>('configuracion');
      
      if (configBox.isNotEmpty) {
        final currentConfig = configBox.getAt(0);
        if (currentConfig != null) {
          final updatedConfig = currentConfig.copyWith(
            tasaCambio: config.tasaCambio,
            lastSyncAt: DateTime.now(),
          );
          await configBox.putAt(0, updatedConfig);
        }
      } else {
        await configBox.add(config);
      }
      print('Configuración sincronizada correctamente.');
    } catch (e) {
      print('Error sincronizando configuración: $e');
    }
  }

  // Sincronizar órdenes offline al servidor
  Future<void> syncOrdenesOffline() async {
    if (!await hasConnection || _isSyncing) return;

    _isSyncing = true;

    try {
      final ordenesBox = Hive.box<Orden>('ordenes');
      final syncQueueBox = Hive.box('sync_queue');

      final ordenesPendientes = ordenesBox.values
          .where((o) => o.isLocal)
          .toList();

      if (ordenesPendientes.isEmpty) {
        _isSyncing = false;
        return;
      }

      final batchSize = 10;
      for (var i = 0; i < ordenesPendientes.length; i += batchSize) {
        final batch = ordenesPendientes.skip(i).take(batchSize).toList();
        
        try {
          final success = await _apiService.syncOrdenes(batch);
          
          if (success) {
            for (var orden in batch) {
              final updatedOrden = orden.copyWith(
                syncedAt: DateTime.now(),
                isLocal: false,
              );
              await ordenesBox.put(orden.id, updatedOrden);
              await syncQueueBox.delete(orden.id);
            }
          }
        } catch (e) {
          print('Error sincronizando lote de órdenes: $e');
        }
      }
    } catch (e) {
      print('Error en sincronización de órdenes: $e');
    } finally {
      _isSyncing = false;
    }
  }

  // Agregar orden a la cola de sincronización
  Future<void> addToSyncQueue(Orden orden) async {
    final syncQueueBox = Hive.box('sync_queue');
    await syncQueueBox.put(orden.id, {
      'orden_id': orden.id,
      'timestamp': DateTime.now().toIso8601String(),
    });
  }

  // Sincronización completa
  Future<void> fullSync() async {
    if (!await hasConnection) {
      print('Sin conexión a internet. No se puede sincronizar.');
      return;
    }

    print('Iniciando sincronización completa...');

    await syncConfiguracion();
    await syncProductos();
    await syncClientes();
    await syncClientesLocales(); // Sincronizar clientes locales
    await syncOrdenesOffline();

    print('Sincronización completada con éxito.');
  }

  void startAutoSync() {
    // Listo para implementar Timer.periodic cuando requieras background sync
  }

  // Sincronización manual (para botón "Sincronizar Manual")
  Future<Map<String, dynamic>> manualSync() async {
    final result = <String, dynamic>{
      'success': false,
      'clientes_synced': 0,
      'ordenes_synced': 0,
      'message': '',
    };

    if (!await hasConnection) {
      result['message'] = 'Sin conexión a internet';
      return result;
    }

    try {
      await fullSync();
      
      final clientesBox = Hive.box<Cliente>('clientes');
      final ordenesBox = Hive.box<Orden>('ordenes');
      
      result['success'] = true;
      result['clientes_synced'] = clientesBox.values.where((c) => !c.isLocal).length;
      result['ordenes_synced'] = ordenesBox.values.where((o) => !o.isLocal).length;
      result['message'] = 'Sincronización completada exitosamente';
      
      return result;
    } catch (e) {
      result['message'] = 'Error durante sincronización: $e';
      return result;
    }
  }
}