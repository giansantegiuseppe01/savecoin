import 'package:hive_flutter/hive_flutter.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'api_service.dart';
import '../models/producto.dart';
import '../models/cliente.dart';
import '../models/orden.dart';
import '../models/configuracion.dart';
import '../utils/log.dart';

/// Sincronización entre Hive (local) y el servidor. Es un único objeto
/// compartido: así la bandera `_isSyncing` evita de verdad dos sincronizaciones
/// simultáneas.
class SyncService {
  static final SyncService _instance = SyncService._internal();

  factory SyncService() => _instance;

  SyncService._internal();

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

      // Reemplazar el catálogo de una sola vez (sin dejar la caja vacía a medias)
      await productosBox.clear();
      await productosBox.putAll({for (final p in productos) p.id: p});

      logd('Sincronizados ${productos.length} productos en Hive.');
    } catch (e) {
      logd('Error sincronizando productos: $e');
    }
  }

  // Sincronizar clientes desde el servidor
  Future<void> syncClientes() async {
    if (!await hasConnection) return;

    try {
      final clientes = await _apiService.getClientes();
      final clientesBox = Hive.box<Cliente>('clientes');

      await clientesBox.putAll({for (final c in clientes) c.id: c});

      logd('Sincronizados ${clientes.length} clientes desde el servidor');
    } catch (e) {
      logd('Error sincronizando clientes: $e');
    }
  }

  // Sincronizar clientes locales al servidor
  Future<void> syncClientesLocales() async {
    if (!await hasConnection || _isSyncing) return;

    _isSyncing = true;

    try {
      final clientesBox = Hive.box<Cliente>('clientes');
      final ordenesBox = Hive.box<Orden>('ordenes');

      final clientesPendientes = clientesBox.values.where((c) => c.isLocal).toList();

      for (var cliente in clientesPendientes) {
        try {
          final clienteDesdeAPI = await _apiService.createCliente(cliente);

          // Reemplazar el cliente local (ID temporal) por el del servidor
          await clientesBox.delete(cliente.id);
          await clientesBox.put(clienteDesdeAPI.id, clienteDesdeAPI);

          // Las órdenes locales que apuntaban al ID temporal pasan al ID real
          final ordenesDelCliente =
              ordenesBox.values.where((o) => o.clienteId == cliente.id).toList();
          for (var orden in ordenesDelCliente) {
            await ordenesBox.put(orden.id, orden.copyWith(clienteId: clienteDesdeAPI.id));
          }

          logd('Cliente sincronizado: ${cliente.nombreCompleto}');
        } catch (e) {
          logd('Error sincronizando cliente ${cliente.nombreCompleto}: $e');
        }
      }
    } catch (e) {
      logd('Error en sincronización de clientes: $e');
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
      logd('Configuración sincronizada correctamente.');
    } catch (e) {
      logd('Error sincronizando configuración: $e');
    }
  }

  // Sincronizar órdenes offline al servidor
  Future<void> syncOrdenesOffline() async {
    if (!await hasConnection || _isSyncing) return;

    _isSyncing = true;

    try {
      final ordenesBox = Hive.box<Orden>('ordenes');
      final syncQueueBox = Hive.box('sync_queue');

      // Solo las cerradas: una orden abierta todavía se está editando
      final ordenesPendientes =
          ordenesBox.values.where((o) => o.isLocal && o.estado != 'abierta').toList();

      const batchSize = 10;
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
          logd('Error sincronizando lote de órdenes: $e');
        }
      }
    } catch (e) {
      logd('Error en sincronización de órdenes: $e');
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
      logd('Sin conexión a internet. No se puede sincronizar.');
      return;
    }

    logd('Iniciando sincronización completa...');

    await syncConfiguracion();
    await syncProductos();
    await syncClientes();
    await syncClientesLocales(); // primero los clientes: las órdenes dependen de ellos
    await syncOrdenesOffline();

    logd('Sincronización completada.');
  }

  // Sincronización manual (para el botón "Sincronizar")
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

      final clientesPendientes = clientesBox.values.where((c) => c.isLocal).length;
      final ordenesPendientes =
          ordenesBox.values.where((o) => o.isLocal && o.estado != 'abierta').length;

      result['clientes_synced'] = clientesBox.values.where((c) => !c.isLocal).length;
      result['ordenes_synced'] = ordenesBox.values.where((o) => !o.isLocal).length;

      // Antes siempre decía "exitosa" aunque quedaran cosas sin enviar.
      if (clientesPendientes == 0 && ordenesPendientes == 0) {
        result['success'] = true;
        result['message'] = 'Sincronización completada exitosamente';
      } else {
        result['message'] = 'Quedan pendientes: $clientesPendientes cliente(s) y '
            '$ordenesPendientes orden(es). Revise la conexión con el servidor.';
      }

      return result;
    } catch (e) {
      result['message'] = 'Error durante sincronización: $e';
      return result;
    }
  }
}
