import 'package:flutter/foundation.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:hive_flutter/hive_flutter.dart';
import '../models/orden.dart';
import '../models/detalle_orden.dart';
import '../models/cliente.dart';
import '../models/configuracion.dart';
import '../services/api_service.dart';
import '../services/sync_service.dart';
import '../services/bluetooth_service.dart';
import '../services/comanda_change_service.dart';

class OrderProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  final SyncService _syncService = SyncService();
  final BluetoothService _bluetoothService = BluetoothService();
  final ComandaChangeService _changeService = ComandaChangeService();
  
  List<Orden> _ordenesAbiertas = [];
  List<Orden> _ordenesCerradas = [];
  Orden? _currentOrden;
  Cliente? _selectedCliente;
  bool _isLoading = false;
  String? _error;
  ComandaChange? _ultimoCambioDetectado;
  List<String> _stockWarnings = [];

  List<Orden> get ordenesAbiertas => _ordenesAbiertas;
  List<Orden> get ordenesCerradas => _ordenesCerradas;
  Orden? get currentOrden => _currentOrden;
  Cliente? get selectedCliente => _selectedCliente;
  bool get isLoading => _isLoading;
  String? get error => _error;
  List<String> get stockWarnings => _stockWarnings;
  SyncService get syncService => _syncService;
  ComandaChange? get ultimoCambioDetectado => _ultimoCambioDetectado;

  // Helper para parsear números que pueden venir como String o num
  double _parseNum(dynamic value) {
    if (value == null) return 0.0;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  Future<void> loadOrdenesAbiertas() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      // Cargar órdenes abiertas desde el backend
      if (await _syncService.hasConnection) {
        try {
          final response = await _apiService.getOrdenes();
          final ordenesData = response['data'] as List;
          
          final ordenes = <Orden>[];
          final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
          
          for (var ordenJson in ordenesData) {
            // Procesar detalles de esta orden
            final detallesBackend = ordenJson['detalles'] as List<dynamic>? ?? [];
            for (var detalleJson in detallesBackend) {
              final detalle = DetalleOrden(
                id: detalleJson['id'].toString(),
                ordenId: ordenJson['id'].toString(),
                productoId: detalleJson['producto_id'].toString(),
                productoNombre: detalleJson['producto_nombre'] as String? ?? '',
                cantidad: _parseNum(detalleJson['cantidad']),
                precioUnitario: _parseNum(detalleJson['precio_unitario']),
                isNew: false,
              );
              detallesBox.put(detalle.id, detalle);
            }
            
            // Crear orden con detalleIds correctos
            final orden = Orden.fromJson(ordenJson);
            final detalleIds = detallesBackend.map((d) => d['id'].toString()).toList();
            
            // Recalcular totales basados en los detalles
            double subtotal = 0;
            for (var detalleJson in detallesBackend) {
              subtotal += _parseNum(detalleJson['subtotal']);
            }
            
            final ordenConDetalles = orden.copyWith(
              detalleIds: detalleIds,
              subtotal: subtotal,
              total: _parseNum(ordenJson['total']) > 0 ? _parseNum(ordenJson['total']) : subtotal,
            );
            ordenes.add(ordenConDetalles);
          }
          
          _ordenesAbiertas = ordenes.where((o) => o.estado == 'abierta').toList();
          
          // Actualizar Hive con las órdenes del backend
          final ordenesBox = Hive.box<Orden>('ordenes');
          for (var orden in ordenes) {
            await ordenesBox.put(orden.id, orden);
          }
        } catch (e) {
          print('Error cargando órdenes del backend: $e');
          // Fallback a Hive
          final ordenesBox = Hive.box<Orden>('ordenes');
          _ordenesAbiertas = ordenesBox.values
              .where((o) => o.estado == 'abierta')
              .toList()
            ..sort((a, b) => b.fechaHora.compareTo(a.fechaHora));
        }
      } else {
        // Sin conexión, cargar desde Hive
        final ordenesBox = Hive.box<Orden>('ordenes');
        _ordenesAbiertas = ordenesBox.values
            .where((o) => o.estado == 'abierta')
            .toList()
          ..sort((a, b) => b.fechaHora.compareTo(a.fechaHora));
      }
      
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadOrdenesCerradas() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      // Cargar órdenes cerradas desde el backend
      if (await _syncService.hasConnection) {
        try {
          final response = await _apiService.getOrdenes();
          final ordenesData = response['data'] as List;
          
          final ordenes = <Orden>[];
          final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
          
          for (var ordenJson in ordenesData) {
            // Procesar detalles de esta orden
            final detallesBackend = ordenJson['detalles'] as List<dynamic>? ?? [];
            for (var detalleJson in detallesBackend) {
              final detalle = DetalleOrden(
                id: detalleJson['id'].toString(),
                ordenId: ordenJson['id'].toString(),
                productoId: detalleJson['producto_id'].toString(),
                productoNombre: detalleJson['producto_nombre'] as String? ?? '',
                cantidad: _parseNum(detalleJson['cantidad']),
                precioUnitario: _parseNum(detalleJson['precio_unitario']),
                isNew: false,
              );
              detallesBox.put(detalle.id, detalle);
            }
            
            // Crear orden con detalleIds correctos
            final orden = Orden.fromJson(ordenJson);
            final detalleIds = detallesBackend.map((d) => d['id'].toString()).toList();
            
            // Recalcular totales basados en los detalles
            double subtotal = 0;
            for (var detalleJson in detallesBackend) {
              subtotal += _parseNum(detalleJson['subtotal']);
            }
            
            final ordenConDetalles = orden.copyWith(
              detalleIds: detalleIds,
              subtotal: subtotal,
              total: _parseNum(ordenJson['total']) > 0 ? _parseNum(ordenJson['total']) : subtotal,
            );
            ordenes.add(ordenConDetalles);
          }
          
          _ordenesCerradas = ordenes.where((o) => o.estado == 'cerrada').toList();
          
          // Actualizar Hive con las órdenes del backend
          final ordenesBox = Hive.box<Orden>('ordenes');
          for (var orden in ordenes) {
            await ordenesBox.put(orden.id, orden);
          }
        } catch (e) {
          print('Error cargando órdenes del backend: $e');
          // Fallback a Hive
          final ordenesBox = Hive.box<Orden>('ordenes');
          _ordenesCerradas = ordenesBox.values
              .where((o) => o.estado == 'cerrada')
              .toList()
            ..sort((a, b) => b.fechaHora.compareTo(a.fechaHora));
        }
      } else {
        // Sin conexión, cargar desde Hive
        final ordenesBox = Hive.box<Orden>('ordenes');
        _ordenesCerradas = ordenesBox.values
            .where((o) => o.estado == 'cerrada')
            .toList()
          ..sort((a, b) => b.fechaHora.compareTo(a.fechaHora));
      }
      
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> createNewOrden(Cliente? cliente, double tasaCambio) async {
    try {
      // CRITICAL FIX: Limpiar completamente el estado anterior antes de crear nueva orden
      _currentOrden = null;
      _selectedCliente = null;
      notifyListeners();

      // Crear orden en el backend directamente (sin numero_recibo, el backend usará el ID)
      if (await _syncService.hasConnection) {
        try {
          final response = await _apiService.createOrden({
            'cliente_id': cliente?.id,
            'cliente_nombre': cliente?.nombreCompleto ?? 'General',
            'estado': 'abierta',
            'detalles': [],
            'metodo_pago': 'efectivo',
            'turno': 'mañana',
          });

          _currentOrden = Orden.fromJson(response['data']);
          print('Orden creada en backend: ID=${_currentOrden!.id}');
        } catch (e) {
          print('Error creando orden en backend: $e');
          // Fallback a Hive si falla el backend
          final orden = Orden(
            id: _generateOrdenId(),
            numeroRecibo: _generateOrdenId(), // Temporal hasta sincronizar
            clienteId: cliente?.id ?? '',
            clienteNombre: cliente?.nombreCompleto ?? 'General',
            tasaCambio: tasaCambio,
            estado: 'abierta',
            isLocal: true,
          );

          final ordenesBox = Hive.box<Orden>('ordenes');
          await ordenesBox.put(orden.id, orden);
          _currentOrden = orden;
          print('Orden creada localmente: ID=${orden.id}');
        }
      } else {
        // Sin conexión, guardar localmente
        final orden = Orden(
          id: _generateOrdenId(),
          numeroRecibo: _generateOrdenId(), // Temporal hasta sincronizar
          clienteId: cliente?.id ?? '',
          clienteNombre: cliente?.nombreCompleto ?? 'General',
          tasaCambio: tasaCambio,
          estado: 'abierta',
          isLocal: true,
        );

        final ordenesBox = Hive.box<Orden>('ordenes');
        await ordenesBox.put(orden.id, orden);
        _currentOrden = orden;
        print('Orden creada localmente (sin conexión): ID=${orden.id}');
      }

      _selectedCliente = cliente;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      print('Error creando orden: $e');
      notifyListeners();
    }
  }

  Future<void> updateOrden(List<DetalleOrden> nuevosDetalles) async {
    if (_currentOrden == null) return;

    try {
      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
      final ordenesBox = Hive.box<Orden>('ordenes');

      // CRITICAL FIX: Implementar lógica diff (UPDATE/DELETE/INSERT)
      
      // 1. Obtener estado anterior desde BD
      final estadoAnterior = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id))
          .toList();

      // 2. Crear mapa para comparación eficiente
      final mapaAnterior = <String, DetalleOrden>{};
      for (var detalle in estadoAnterior) {
        mapaAnterior[detalle.productoId] = detalle;
      }

      // 3. Procesar cambios diff
      final detalleIdsNuevos = <String>[];
      
      for (var nuevoDetalle in nuevosDetalles) {
        final detalleAnterior = mapaAnterior[nuevoDetalle.productoId];
        
        if (detalleAnterior == null) {
          // INSERT: Nuevo producto en la comanda (marcar como nuevo para impresión)
          final detalleNuevo = nuevoDetalle.copyWith(isNew: true);
          await detallesBox.put(detalleNuevo.id, detalleNuevo);
          detalleIdsNuevos.add(detalleNuevo.id);
        } else {
          // UPDATE: Producto existente con posible cambio de cantidad
          if (detalleAnterior.cantidad != nuevoDetalle.cantidad) {
            final detalleActualizado = detalleAnterior.copyWith(
              cantidad: nuevoDetalle.cantidad,
              // subtotal se recalcula automáticamente
            );
            await detallesBox.put(detalleAnterior.id, detalleActualizado);
          }
          detalleIdsNuevos.add(detalleAnterior.id);
        }
      }

      // 4. DELETE: Eliminar productos que ya no están en el carrito
      for (var detalleAnterior in estadoAnterior) {
        final productoId = detalleAnterior.productoId;
        final existeEnNuevos = nuevosDetalles.any((d) => d.productoId == productoId);
        
        if (!existeEnNuevos) {
          await detallesBox.delete(detalleAnterior.id);
        }
      }

      // 5. Actualizar lista de detalleIds de la orden
      _currentOrden!.detalleIds = detalleIdsNuevos;

      // 6. Detectar cambios para impresión de comanda modificada
      final cambios = _changeService.detectarCambios(
        estadoAnterior,
        nuevosDetalles,
        _currentOrden!.numeroRecibo,
      );
      _ultimoCambioDetectado = cambios;

      // 7. Recalcular totales
      final allDetalles = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id))
          .toList();

      final subtotal = allDetalles.fold(0.0, (sum, d) => sum + d.subtotal);
      final impuesto = _currentOrden!.aplicaIva ? subtotal * 0.16 : 0.0;
      final total = subtotal + impuesto;

      _currentOrden = _currentOrden!.copyWith(
        subtotal: subtotal,
        impuesto: impuesto,
        total: total,
      );

      await ordenesBox.put(_currentOrden!.id, _currentOrden!);

      // 8. PASO 5B: Si se detectaron cambios, imprimir tickets separados
      if (cambios.tieneCambios) {
        try {
          // Imprimir productos añadidos
          if (cambios.tieneAgregaciones) {
            await _bluetoothService.printProductosAnadidos(
              _currentOrden!,
              cambios.detallesAgregados,
            );
          }

          // Imprimir productos eliminados
          if (cambios.tieneEliminaciones) {
            await _bluetoothService.printProductosEliminados(
              _currentOrden!,
              cambios.detallesEliminados,
            );
          }

          // Resetear flags de modificación después de imprimir
          for (var detalleId in _currentOrden!.detalleIds) {
            final detalle = detallesBox.get(detalleId);
            if (detalle != null && detalle.fueModificado) {
              final detalleReset = detalle.copyWith(fueModificado: false);
              await detallesBox.put(detalleId, detalleReset);
            }
          }
        } catch (e) {
          // No interrumpir el flujo si falla la impresión
        }
      }

      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  // PASO 4: Actualizar notas de la orden
  void updateOrdenNotas(String notas) {
    if (_currentOrden == null) return;

    final notasExistentes = _currentOrden!.notas ?? '';
    final nuevasNotas = notasExistentes.isEmpty
        ? notas
        : '$notasExistentes - $notas';

    _currentOrden = _currentOrden!.copyWith(notas: nuevasNotas);
    notifyListeners();
  }

  // PASO 4: Limpiar notas de la orden después de imprimir
  Future<void> clearOrdenNotas() async {
    if (_currentOrden == null) return;

    _currentOrden = _currentOrden!.copyWith(notas: null);
    
    final ordenesBox = Hive.box<Orden>('ordenes');
    await ordenesBox.put(_currentOrden!.id, _currentOrden!);
    
    notifyListeners();
  }

  // Editar comanda existente (reemplazar completamente los detalles)
  Future<void> editarComandaCompleta(List<DetalleOrden> nuevosDetalles) async {
    if (_currentOrden == null) return;

    try {
      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
      final ordenesBox = Hive.box<Orden>('ordenes');

      // Obtener detalles anteriores para generar tickets de anulación si es necesario
      final detallesAnteriores = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id))
          .toList();

      // Borrar todos los detalles existentes de la orden
      for (var detalleId in _currentOrden!.detalleIds) {
        await detallesBox.delete(detalleId);
      }

      // Limpiar la lista de detalleIds
      _currentOrden!.detalleIds.clear();

      // Guardar nuevos detalles
      for (var detalle in nuevosDetalles) {
        await detallesBox.put(detalle.id, detalle);
        _currentOrden!.detalleIds.add(detalle.id);
      }

      // Generar tickets de anulación para productos eliminados o reducidos
      for (var detalleAnterior in detallesAnteriores) {
        final nuevoDetalle = nuevosDetalles
            .where((d) => d.productoId == detalleAnterior.productoId)
            .firstOrNull;
        
        if (nuevoDetalle == null) {
          // Producto fue eliminado completamente
          await _imprimirTicketAnulacion(detalleAnterior, detalleAnterior.cantidad);
        } else if (nuevoDetalle.cantidad < detalleAnterior.cantidad) {
          // Cantidad fue reducida
          final cantidadEliminada = detalleAnterior.cantidad - nuevoDetalle.cantidad;
          await _imprimirTicketAnulacion(detalleAnterior, cantidadEliminada);
        }
      }

      // Recalcular totales
      final allDetalles = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id))
          .toList();

      final subtotal = allDetalles.fold(0.0, (sum, d) => sum + d.subtotal);
      final impuesto = subtotal * 0.16;
      final total = subtotal + impuesto;

      _currentOrden = _currentOrden!.copyWith(
        subtotal: subtotal,
        impuesto: impuesto,
        total: total,
      );

      // Sincronizar con API si hay conexión
      if (await _syncService.hasConnection) {
        try {
          final response = await _apiService.updateOrden(_currentOrden!.id, _currentOrden!);
          
          // Verificar si hay advertencias de stock
          if (response['advertencias'] != null && response['advertencias'] is List) {
            final advertencias = response['advertencias'] as List;
            print('Advertencias de stock: $advertencias');
            // Las advertencias se mostrarán en el UI
          }
          
          _currentOrden = Orden.fromJson(response['data']).copyWith(isLocal: false, syncedAt: DateTime.now());
        } catch (e) {
          print('Error sincronizando edición: $e');
          _currentOrden = _currentOrden!.copyWith(isLocal: true);
        }
      } else {
        _currentOrden = _currentOrden!.copyWith(isLocal: true);
      }

      await ordenesBox.put(_currentOrden!.id, _currentOrden!);
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  // Imprimir ticket de anulación para cocina/barra
  Future<void> _imprimirTicketAnulacion(DetalleOrden detalle, double cantidadEliminada) async {
    try {
      await _bluetoothService.printAnulacionTicket(
        _currentOrden!,
        detalle,
        cantidadEliminada,
      );
    } catch (e) {
      // Handle Web simulation
      if (e.toString().contains('WEB_SIMULATION:anulacion')) {
        rethrow;
      }
      print('Error imprimiendo ticket de anulación: $e');
    }
  }

  Future<void> sendToKitchen() async {
    if (_currentOrden == null) return;

    try {
      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
      final detalles = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id) && d.isNew)
          .toList();

      // Sincronizar orden con el backend si hay conexión
      if (await _syncService.hasConnection) {
        try {
          final detallesParaBackend = detalles.map((d) => {
            'producto_id': int.parse(d.productoId),
            'producto_nombre': d.productoNombre,
            'precio_unitario': d.precioUnitario,
            'cantidad': d.cantidad,
          }).toList();

          await _apiService.updateOrden(_currentOrden!.id, {
            'estado': 'abierta',
            'detalles': detallesParaBackend,
            'subtotal': _currentOrden!.subtotal,
            'total': _currentOrden!.total,
          });
        } catch (e) {
          print('Error sincronizando orden con backend: $e');
        }
      }

      // Imprimir comanda de cocina
      await _bluetoothService.printKitchenCommand(_currentOrden!, detalles);

      // Marcar detalles como no nuevos
      for (var detalle in detalles) {
        final updated = detalle.copyWith(isNew: false);
        await detallesBox.put(detalle.id, updated);
      }

      // Limpiar notas del cliente después de imprimir
      _currentOrden = _currentOrden!.copyWith(notas: null);
      final ordenesBox = Hive.box<Orden>('ordenes');
      await ordenesBox.put(_currentOrden!.id, _currentOrden!);

      notifyListeners();
    } catch (e) {
      // Check if it's a Web simulation exception
      if (e.toString().contains('WEB_SIMULATION:kitchen_command')) {
        // Re-throw to let UI handle the modal
        rethrow;
      }
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<void> updateKitchenCommand() async {
    if (_currentOrden == null) return;

    try {
      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
      final detalles = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id) && d.isNew)
          .toList();

      if (detalles.isEmpty) {
        _error = 'No hay items nuevos para imprimir';
        notifyListeners();
        return;
      }

      // Imprimir solo items nuevos
      await _bluetoothService.printKitchenCommand(_currentOrden!, detalles, onlyNew: true);

      // Marcar detalles como no nuevos
      for (var detalle in detalles) {
        final updated = detalle.copyWith(isNew: false);
        await detallesBox.put(detalle.id, updated);
      }

      notifyListeners();
    } catch (e) {
      // Check if it's a Web simulation exception
      if (e.toString().contains('WEB_SIMULATION:kitchen_command')) {
        rethrow;
      }
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<void> printPreCuenta(double tasaCambio) async {
    if (_currentOrden == null) return;

    try {
      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
      final detalles = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id))
          .toList();

      await _bluetoothService.printPreCuenta(_currentOrden!, detalles, tasaCambio);
    } catch (e) {
      // Check if it's a Web simulation exception
      if (e.toString().contains('WEB_SIMULATION:pre_cuenta')) {
        rethrow;
      }
      _error = e.toString();
      notifyListeners();
    }
  }

  // PASO 6: Cerrar orden con información de pago
  Future<void> closeOrden({
    required String metodoPago,
    double? montoEntregado,
    String? referencia,
  }) async {
    if (_currentOrden == null) return;

    try {
      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
      final detalles = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id))
          .toList();

      // Obtener configuración desde Hive
      final configBox = Hive.box<Configuracion>('configuracion');
      final config = configBox.get('config');
      final tasaCambio = config?.tasaCambio ?? 36.5;

      // CRITICAL FIX: En Web, no intentar imprimir realmente
      if (!kIsWeb) {
        try {
          await _bluetoothService.printFinalTicket(_currentOrden!, detalles, tasaCambio, metodoPago);
        } catch (e) {
          print('Error al imprimir ticket (ignorado en Web): $e');
        }
      }

      // PASO 6: Actualizar orden con información de pago
      _currentOrden = _currentOrden!.copyWith(
        estado: 'cerrada',
        metodoPago: metodoPago,
      );

      // Agregar notas con información de pago si existe
      final notasPago = <String>[];
      if (montoEntregado != null) {
        notasPago.add('Efectivo: \$${montoEntregado.toStringAsFixed(2)}');
      }
      if (referencia != null && referencia.isNotEmpty) {
        notasPago.add('Ref: $referencia');
      }

      if (notasPago.isNotEmpty) {
        final notasExistentes = _currentOrden!.notas ?? '';
        final nuevasNotas = notasExistentes.isEmpty
            ? notasPago.join(', ')
            : '$notasExistentes - ${notasPago.join(', ')}';
        _currentOrden = _currentOrden!.copyWith(notas: nuevasNotas);
      }

      // Intentar sincronizar con API primero (Online First)
      if (await _syncService.hasConnection) {
        try {
          final response = await _apiService.updateOrden(_currentOrden!.id, _currentOrden!);

          // Verificar si hay advertencias de stock (response es un Map)
          if (response['advertencias'] != null && response['advertencias'] is List) {
            final advertencias = response['advertencias'] as List;
            print('Advertencias de stock: $advertencias');
            // Guardar advertencias para mostrarlas en el UI
            _stockWarnings = advertencias.cast<String>();
          } else {
            _stockWarnings = [];
          }

          _currentOrden = Orden.fromJson(response['data']).copyWith(isLocal: false, syncedAt: DateTime.now());
          print('Orden sincronizada con API: ${_currentOrden!.numeroRecibo}');

          // Eliminar orden local de Hive para evitar duplicados
          final ordenesBox = Hive.box<Orden>('ordenes');
          await ordenesBox.delete(_currentOrden!.id);
        } catch (e) {
          print('Error al actualizar orden en API, guardando localmente: $e');
          _currentOrden = _currentOrden!.copyWith(isLocal: true);
        }
      } else {
        print('Sin conexión, guardando orden localmente');
        _currentOrden = _currentOrden!.copyWith(isLocal: true);
      }

      final ordenesBox = Hive.box<Orden>('ordenes');
      await ordenesBox.put(_currentOrden!.id, _currentOrden!);

      // Agregar a cola de sincronización si quedó local
      if (_currentOrden!.isLocal) {
        await _syncService.addToSyncQueue(_currentOrden!);
      }

      // Limpiar orden actual
      _currentOrden = null;
      _selectedCliente = null;

      // Recargar órdenes abiertas y cerradas
      await loadOrdenesAbiertas();
      await loadOrdenesCerradas();

      notifyListeners();
    } catch (e) {
      _error = e.toString();
      print('Error en closeOrden: $e');
      notifyListeners();
    }
  }

  void clearStockWarnings() {
    _stockWarnings = [];
    notifyListeners();
  }

  // Método para cerrar orden con IVA personalizado
  Future<void> closeOrdenConIva(String metodoPago, double tasaCambio, bool aplicaIva) async {
    if (_currentOrden == null) return;

    try {
      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
      final detalles = detallesBox.values
          .where((d) => _currentOrden!.detalleIds.contains(d.id))
          .toList();

      // Calcular IVA y total según aplicaIva
      final tasaIva = 0.16;
      final impuestoCalculado = aplicaIva ? _currentOrden!.subtotal * tasaIva : 0.0;
      final totalCalculado = _currentOrden!.subtotal + impuestoCalculado;

      // CRITICAL FIX: Mantener el mismo ID, solo cambiar estado y totales
      _currentOrden = _currentOrden!.copyWith(
        aplicaIva: aplicaIva,
        impuesto: impuestoCalculado,
        total: totalCalculado,
        estado: 'cerrada',
        metodoPago: metodoPago,
      );

      // Imprimir ticket final (con manejo de error en Web)
      if (!kIsWeb) {
        try {
          await _bluetoothService.printFinalTicket(_currentOrden!, detalles, tasaCambio, metodoPago);
        } catch (e) {
          print('Error al imprimir ticket (ignorado): $e');
        }
      }

      // Intentar sincronizar con API primero (Online First)
      if (await _syncService.hasConnection) {
        try {
          final response = await _apiService.updateOrden(_currentOrden!.id, _currentOrden!);
          
          // Verificar si hay advertencias de stock (response es un Map)
          if (response['advertencias'] != null && response['advertencias'] is List) {
            final advertencias = response['advertencias'] as List;
            print('Advertencias de stock: $advertencias');
            _stockWarnings = advertencias.cast<String>();
          } else {
            _stockWarnings = [];
          }
          
          _currentOrden = Orden.fromJson(response['data']).copyWith(isLocal: false, syncedAt: DateTime.now());
          print('Orden sincronizada con servidor. ID local: ${_currentOrden!.id}, ID servidor: ${response['data']['id']}');
          
          // Eliminar orden local de Hive para evitar duplicados
          final ordenesBox = Hive.box<Orden>('ordenes');
          await ordenesBox.delete(_currentOrden!.id);
        } catch (e) {
          print('Error al actualizar orden en API, guardando localmente: $e');
          _currentOrden = _currentOrden!.copyWith(isLocal: true);
        }
      } else {
        _currentOrden = _currentOrden!.copyWith(isLocal: true);
      }

      final ordenesBox = Hive.box<Orden>('ordenes');
      await ordenesBox.put(_currentOrden!.id, _currentOrden!);

      // Agregar a cola de sincronización si quedó local
      if (_currentOrden!.isLocal) {
        await _syncService.addToSyncQueue(_currentOrden!);
      }

      // Limpiar orden actual
      _currentOrden = null;
      _selectedCliente = null;

      // Recargar órdenes abiertas y cerradas
      await loadOrdenesAbiertas();
      await loadOrdenesCerradas();

      notifyListeners();
    } catch (e) {
      _error = e.toString();
      print('Error en closeOrdenConIva: $e');
      notifyListeners();
    }
  }

  void selectOrden(Orden orden) {
    _currentOrden = orden;
    notifyListeners();
  }

  void updateCurrentOrden(Orden orden) {
    _currentOrden = orden;
    notifyListeners();
  }

  void selectCliente(Cliente? cliente) {
    _selectedCliente = cliente;
    notifyListeners();
  }

  void clearCliente() {
    _selectedCliente = null;
    notifyListeners();
  }

  void clearCurrentOrden() {
    _currentOrden = null;
    _selectedCliente = null;
    notifyListeners();
  }

  String _generateOrdenId() {
    // Generar un ID único basado en timestamp + random para evitar colisiones
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final random = (timestamp % 1000);
    return (timestamp + random).toString();
  }
}
