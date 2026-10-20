import 'package:flutter/foundation.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';
import '../models/cliente.dart';
import '../models/detalle_orden.dart';
import '../models/orden.dart';
import '../services/api_service.dart';
import '../services/bluetooth_service.dart';
import '../services/comanda_change_service.dart';
import '../services/sync_service.dart';
import '../utils/constants.dart';
import '../utils/formatters.dart';
import '../utils/log.dart';

/// Ticket que la pantalla debe mostrar como vista previa (solo en Web, donde no
/// hay impresora Bluetooth).
class TicketPreview {
  final Orden orden;
  final List<DetalleOrden> detalles;

  /// 'comanda', 'pre_cuenta' o 'final'
  final String tipo;
  final String? metodoPago;

  const TicketPreview({
    required this.orden,
    required this.detalles,
    required this.tipo,
    this.metodoPago,
  });
}

/// Resultado de una acción del POS (enviar comanda, cobrar, imprimir...).
///
/// [ok] indica si lo principal (guardar la orden / registrar el cobro) quedó
/// hecho. Si la impresión falla pero la orden se guardó, [ok] es true y
/// [mensaje] explica qué pasó: así no se avisa "venta completada" cuando no lo
/// está, ni se pierde una venta por un problema de la impresora.
class AccionResultado {
  final bool ok;
  final String? mensaje;
  final TicketPreview? preview;
  final List<String> advertencias;

  const AccionResultado({
    required this.ok,
    this.mensaje,
    this.preview,
    this.advertencias = const [],
  });
}

class _Impresion {
  final bool simulada;
  final String? error;
  const _Impresion({this.simulada = false, this.error});
  bool get ok => !simulada && error == null;
}

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

  List<Orden> get ordenesAbiertas => _ordenesAbiertas;
  List<Orden> get ordenesCerradas => _ordenesCerradas;
  Orden? get currentOrden => _currentOrden;
  Cliente? get selectedCliente => _selectedCliente;
  bool get isLoading => _isLoading;
  String? get error => _error;
  SyncService get syncService => _syncService;

  Box<Orden> get _ordenesBox => Hive.box<Orden>('ordenes');
  Box<DetalleOrden> get _detallesBox => Hive.box<DetalleOrden>('detalles_orden');

  // ---------------------------------------------------------------------------
  // Lectura
  // ---------------------------------------------------------------------------

  /// Detalles (guardados en Hive) de una orden, en el orden de la comanda.
  List<DetalleOrden> detallesDe(Orden orden) {
    return orden.detalleIds
        .map(_detallesBox.get)
        .whereType<DetalleOrden>()
        .toList();
  }

  /// Carga las órdenes del servidor (si hay conexión) y las combina con las que
  /// están pendientes de enviar en este dispositivo. Reemplaza a las dos
  /// funciones casi idénticas que había antes para abiertas y cerradas.
  Future<void> loadOrdenes() async {
    if (_isLoading) return;
    _isLoading = true;
    _error = null;
    notifyListeners();

    Set<String>? idsServidor;
    try {
      if (await _syncService.hasConnection) {
        try {
          final response = await _apiService.getOrdenes();
          final data = response['data'];
          if (data is List) {
            idsServidor = <String>{};
            for (final item in data.whereType<Map>()) {
              final json = Map<String, dynamic>.from(item);
              final id = Orden.fromJson(json).id;
              idsServidor.add(id);
              // Una orden con cambios sin enviar es más reciente que la del servidor
              if (_ordenesBox.get(id)?.isLocal ?? false) continue;
              await _guardarDesdeServidor(json);
            }
          }
        } catch (e) {
          logd('No se pudieron cargar las órdenes del servidor: $e');
          idsServidor = null;
        }
      }
      _refrescarListas(idsServidor);
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadOrdenesAbiertas() => loadOrdenes();

  Future<void> loadOrdenesCerradas() => loadOrdenes();

  void _refrescarListas([Set<String>? idsServidor]) {
    final todas = _ordenesBox.values
        .where((o) => idsServidor == null || idsServidor.contains(o.id) || o.isLocal)
        .toList()
      ..sort((a, b) => b.fechaHora.compareTo(a.fechaHora));
    _ordenesAbiertas = todas.where((o) => o.estado == 'abierta').toList();
    _ordenesCerradas = todas.where((o) => o.estado == 'cerrada').toList();
  }

  // ---------------------------------------------------------------------------
  // Selección de orden / cliente
  // ---------------------------------------------------------------------------

  /// Deja una orden abierta como la orden en edición (y restaura su cliente,
  /// que antes se perdía y bloqueaba "Enviar a cocina").
  void selectOrden(Orden orden) {
    _currentOrden = orden;
    _selectedCliente = _clienteDe(orden);
    notifyListeners();
  }

  Cliente? _clienteDe(Orden orden) {
    if (orden.clienteId.isEmpty) return null;
    final guardado = Hive.box<Cliente>('clientes').get(orden.clienteId);
    if (guardado != null) return guardado;
    return Cliente(
      id: orden.clienteId,
      nombre: orden.clienteNombre,
      apellido: '',
      cedula: '',
      isLocal: false,
    );
  }

  void selectCliente(Cliente? cliente) {
    _selectedCliente = cliente;
    notifyListeners();
  }

  void clearCliente() {
    _selectedCliente = null;
    notifyListeners();
  }

  /// Descarta la orden en edición (no la borra: solo deja de editarse).
  void clearCurrentOrden() {
    _currentOrden = null;
    _selectedCliente = null;
    notifyListeners();
  }

  // ---------------------------------------------------------------------------
  // Acciones del POS
  // ---------------------------------------------------------------------------

  /// Guarda la comanda y la envía a cocina.
  ///
  /// Imprime solo lo que cambió respecto a lo último que se envió: la comanda
  /// completa la primera vez, y después los productos añadidos o los
  /// eliminados/reducidos. No se imprime nada al tocar +/- en el carrito.
  Future<AccionResultado> enviarComanda({
    required List<DetalleOrden> detalles,
    required Cliente cliente,
    required double tasaCambio,
    bool aplicaIva = false,
    String? notas,
  }) async {
    try {
      var orden = await _asegurarOrden(cliente, tasaCambio);
      final idAntes = orden.id;
      final anteriores = detallesDe(orden);

      orden = await _guardarDetalles(orden, detalles, aplicaIva: aplicaIva);
      if (notas != null && notas.trim().isNotEmpty) {
        orden = orden.copyWith(notas: notas.trim());
      }

      final actuales = detallesDe(orden);
      final advertencias = <String>[];
      orden = await _sincronizar(orden, advertencias);

      final cambios =
          _changeService.detectarCambios(anteriores, actuales, orden.numeroRecibo);
      // Solo cuenta como "ya enviada" si la comanda se imprimió de verdad
      final yaEnviada = anteriores.isNotEmpty && await _comandaImpresa(idAntes);

      // Qué se imprime: la comanda completa o solo la diferencia
      final impresion = await _imprimirComanda(orden, actuales, cambios, yaEnviada);
      if (impresion.error == null && (actuales.isNotEmpty || yaEnviada)) {
        await _marcarComandaImpresa(orden.id);
      }

      // Las notas del cliente son de este envío: no se arrastran al siguiente.
      // El ticket (o su vista previa) se arma con la orden que aún las tiene.
      final ordenConNotas = orden;
      orden = orden.copyWith(clearNotas: true);
      await _ordenesBox.put(orden.id, orden);

      // La orden queda guardada: el POS vuelve a empezar para la siguiente comanda
      _currentOrden = null;
      _selectedCliente = null;
      _refrescarListas();
      notifyListeners();

      if (impresion.simulada) {
        return AccionResultado(
          ok: true,
          advertencias: advertencias,
          preview: TicketPreview(
            orden: ordenConNotas,
            detalles: yaEnviada ? _soloCambios(cambios, actuales) : actuales,
            tipo: 'comanda',
          ),
        );
      }
      return AccionResultado(
        ok: true,
        advertencias: advertencias,
        mensaje: impresion.error == null
            ? (cambios.tieneCambios || !yaEnviada
                ? 'Comanda enviada a cocina'
                : 'La comanda no tenía cambios')
            : 'Comanda guardada, pero no se pudo imprimir: ${impresion.error}',
      );
    } catch (e) {
      logd('Error enviando la comanda: $e');
      _error = e.toString();
      notifyListeners();
      return AccionResultado(ok: false, mensaje: 'No se pudo guardar la comanda: $e');
    }
  }

  /// Cobra la orden: la guarda, la cierra con el método de pago y, solo si todo
  /// salió bien, imprime el ticket final.
  Future<AccionResultado> cobrar({
    required List<DetalleOrden> detalles,
    required Cliente cliente,
    required double tasaCambio,
    required String metodoPago,
    required bool aplicaIva,
    double? montoEntregado,
    String? referencia,
  }) async {
    try {
      var orden = await _asegurarOrden(cliente, tasaCambio);
      orden = await _guardarDetalles(orden, detalles, aplicaIva: aplicaIva);

      final notasPago = <String>[
        if (montoEntregado != null) 'Efectivo: \$${montoEntregado.toStringAsFixed(2)}',
        if (referencia != null && referencia.isNotEmpty) 'Ref: $referencia',
      ];
      final notasPrevias = orden.notas ?? '';
      orden = orden.copyWith(
        estado: 'cerrada',
        metodoPago: metodoPago,
        tasaCambio: tasaCambio,
        notas: notasPago.isEmpty
            ? null
            : (notasPrevias.isEmpty
                ? notasPago.join(', ')
                : '$notasPrevias - ${notasPago.join(', ')}'),
      );

      final advertencias = <String>[];
      orden = await _sincronizar(orden, advertencias);

      _currentOrden = null;
      _selectedCliente = null;
      _refrescarListas();
      notifyListeners();

      final cerrada = detallesDe(orden);
      final pendiente = orden.isLocal
          ? 'Venta guardada sin conexión: se enviará al servidor al sincronizar.'
          : null;

      final impresion = await _imprimir(() =>
          _bluetoothService.printFinalTicket(orden, cerrada, tasaCambio, metodoPago));

      if (impresion.simulada) {
        return AccionResultado(
          ok: true,
          mensaje: pendiente,
          advertencias: advertencias,
          preview: TicketPreview(
            orden: orden,
            detalles: cerrada,
            tipo: 'final',
            metodoPago: metodoPago,
          ),
        );
      }
      final partes = <String>[
        'Venta completada',
        ?pendiente,
        if (impresion.error != null)
          'No se pudo imprimir el ticket (${impresion.error}). '
              'Puede reimprimirlo desde Órdenes Cerradas.',
      ];
      return AccionResultado(
        ok: true,
        mensaje: partes.join('. '),
        advertencias: advertencias,
      );
    } catch (e) {
      logd('Error cobrando la orden: $e');
      _error = e.toString();
      notifyListeners();
      return AccionResultado(ok: false, mensaje: 'No se pudo registrar el cobro: $e');
    }
  }

  /// Imprime la pre-cuenta de lo que hay en el carrito. No guarda nada: la orden
  /// solo cambia al enviar la comanda o al cobrar, así no se pierde la diferencia
  /// que hay que mandar a cocina.
  Future<AccionResultado> imprimirPreCuenta({
    required List<DetalleOrden> detalles,
    required Cliente? cliente,
    required double tasaCambio,
    bool aplicaIva = false,
  }) async {
    final base = _currentOrden ??
        Orden(
          numeroRecibo: 'PRE-CUENTA',
          clienteId: cliente?.id ?? '',
          clienteNombre: cliente?.nombreCompleto ?? 'General',
          tasaCambio: tasaCambio,
        );
    final subtotal = _redondear(detalles.fold(0.0, (s, d) => s + d.subtotal));
    final impuesto = aplicaIva ? _redondear(subtotal * kIvaRate) : 0.0;
    final orden = base.copyWith(
      clienteNombre: cliente?.nombreCompleto ?? base.clienteNombre,
      subtotal: subtotal,
      impuesto: impuesto,
      total: subtotal + impuesto,
      aplicaIva: aplicaIva,
      tasaCambio: tasaCambio,
    );
    return _imprimirPreCuentaDe(orden, detalles, tasaCambio);
  }

  /// Pre-cuenta de una orden abierta ya guardada.
  Future<AccionResultado> imprimirPreCuentaDeOrden(Orden orden, double tasaCambio) =>
      _imprimirPreCuentaDe(orden, detallesDe(orden), tasaCambio);

  Future<AccionResultado> _imprimirPreCuentaDe(
    Orden orden,
    List<DetalleOrden> detalles,
    double tasaCambio,
  ) async {
    final impresion = await _imprimir(
        () => _bluetoothService.printPreCuenta(orden, detalles, tasaCambio));
    if (impresion.simulada) {
      return AccionResultado(
        ok: true,
        preview: TicketPreview(orden: orden, detalles: detalles, tipo: 'pre_cuenta'),
      );
    }
    if (impresion.error != null) {
      return AccionResultado(
          ok: false, mensaje: 'No se pudo imprimir: ${impresion.error}');
    }
    return const AccionResultado(ok: true, mensaje: 'Pre-cuenta impresa');
  }

  /// Vuelve a imprimir la comanda completa de una orden abierta.
  Future<AccionResultado> reimprimirComanda(Orden orden) async {
    final detalles = detallesDe(orden);
    if (detalles.isEmpty) {
      return const AccionResultado(ok: false, mensaje: 'La orden no tiene productos');
    }
    final impresion =
        await _imprimir(() => _bluetoothService.printKitchenCommand(orden, detalles));
    if (impresion.simulada) {
      return AccionResultado(
        ok: true,
        preview: TicketPreview(orden: orden, detalles: detalles, tipo: 'comanda'),
      );
    }
    if (impresion.error != null) {
      return AccionResultado(
          ok: false, mensaje: 'No se pudo imprimir: ${impresion.error}');
    }
    return const AccionResultado(ok: true, mensaje: 'Comanda reimpresa');
  }

  /// Vuelve a imprimir el ticket de una orden cerrada.
  Future<AccionResultado> reimprimirTicket(Orden orden, double tasaCambio) async {
    final detalles = detallesDe(orden);
    final metodo = orden.metodoPago ?? 'efectivo';
    final impresion = await _imprimir(
        () => _bluetoothService.reprintTicket(orden, detalles, tasaCambio, metodo));
    if (impresion.simulada) {
      return AccionResultado(
        ok: true,
        preview: TicketPreview(
          orden: orden,
          detalles: detalles,
          tipo: 'reprint',
          metodoPago: metodo,
        ),
      );
    }
    if (impresion.error != null) {
      return AccionResultado(
          ok: false, mensaje: 'No se pudo imprimir: ${impresion.error}');
    }
    return const AccionResultado(ok: true, mensaje: 'Ticket reimpreso');
  }

  // ---------------------------------------------------------------------------
  // Internos
  // ---------------------------------------------------------------------------

  /// Devuelve la orden en edición o crea una nueva (en el servidor si hay
  /// conexión; si no, solo en este dispositivo).
  Future<Orden> _asegurarOrden(Cliente cliente, double tasaCambio) async {
    final actual = _currentOrden;
    if (actual != null) {
      // El cliente pudo cambiarse en el POS después de abrir la orden
      if (actual.clienteId != cliente.id) {
        final cambiada = actual.copyWith(
          clienteId: cliente.id,
          clienteNombre: cliente.nombreCompleto,
        );
        _currentOrden = cambiada;
        return cambiada;
      }
      return actual;
    }

    Orden? creada;
    if (await _syncService.hasConnection) {
      try {
        final response = await _apiService.createOrden({
          // Un cliente creado sin conexión todavía no existe en el servidor
          if (int.tryParse(cliente.id) != null) 'cliente_id': int.parse(cliente.id),
          'cliente_nombre': cliente.nombreCompleto,
          'estado': 'abierta',
          'tasa_cambio': tasaCambio,
          'detalles': [],
          'metodo_pago': 'efectivo',
          'turno': 'mañana',
        });
        creada = await _guardarDesdeServidor(Map<String, dynamic>.from(response['data'] as Map));
        // fromJson no conoce el nombre que usamos al crearla
        creada = creada.copyWith(
          clienteId: cliente.id,
          clienteNombre: cliente.nombreCompleto,
          tasaCambio: tasaCambio,
        );
      } catch (e) {
        logd('No se pudo crear la orden en el servidor, se crea local: $e');
      }
    }

    creada ??= () {
      final id = const Uuid().v4();
      return Orden(
        id: id,
        numeroRecibo: 'LOC-${id.substring(0, 8).toUpperCase()}',
        clienteId: cliente.id,
        clienteNombre: cliente.nombreCompleto,
        tasaCambio: tasaCambio,
        isLocal: true,
      );
    }();

    await _ordenesBox.put(creada.id, creada);
    _currentOrden = creada;
    return creada;
  }

  /// Reemplaza los detalles de la orden por los del carrito (conservando el id
  /// de los productos que ya estaban) y recalcula los totales.
  Future<Orden> _guardarDetalles(
    Orden orden,
    List<DetalleOrden> nuevos, {
    required bool aplicaIva,
  }) async {
    final previos = {for (final d in detallesDe(orden)) d.productoId: d};
    final ids = <String>[];

    for (final n in nuevos) {
      final previo = previos[n.productoId];
      final detalle = DetalleOrden(
        id: previo?.id,
        ordenId: orden.id,
        productoId: n.productoId,
        productoNombre: n.productoNombre,
        precioUnitario: n.precioUnitario,
        cantidad: n.cantidad,
        notas: n.notas,
        isLocal: previo?.isLocal ?? true,
        isNew: false,
      );
      await _detallesBox.put(detalle.id, detalle);
      ids.add(detalle.id);
    }
    for (final previo in previos.values) {
      if (!ids.contains(previo.id)) await _detallesBox.delete(previo.id);
    }

    final subtotal =
        _redondear(ids.fold(0.0, (s, id) => s + (_detallesBox.get(id)?.subtotal ?? 0)));
    final impuesto = aplicaIva ? _redondear(subtotal * kIvaRate) : 0.0;

    final actualizada = orden.copyWith(
      detalleIds: ids,
      subtotal: subtotal,
      impuesto: impuesto,
      total: subtotal + impuesto,
      aplicaIva: aplicaIva,
    );
    await _ordenesBox.put(actualizada.id, actualizada);
    _currentOrden = actualizada;
    return actualizada;
  }

  /// Envía la orden (con TODOS sus detalles: el servidor reemplaza los que
  /// tenía) y adopta lo que responde. Sin conexión o si falla, la orden queda
  /// pendiente en este dispositivo.
  Future<Orden> _sincronizar(Orden orden, List<String> advertencias) async {
    final detalles = detallesDe(orden);
    var resultado = orden;

    if (await _syncService.hasConnection) {
      try {
        logd('[orden ${orden.id}] envío: ${_resumen(detalles)}');
        final response =
            await _apiService.updateOrden(orden.id, _payload(orden, detalles));
        final devueltos = (response['data'] is Map ? response['data']['detalles'] : null);
        logd('[orden ${orden.id}] servidor devolvió: '
            '${devueltos is List ? devueltos.map((d) => "${d['producto_nombre']} x${d['cantidad']}").join(', ') : 'sin detalles'}');

        final adv = response['advertencias'];
        if (adv is List) advertencias.addAll(adv.map((e) => e.toString()));

        final guardada = await _guardarDesdeServidor(
          Map<String, dynamic>.from(response['data'] as Map),
          conservar: orden,
        );
        if (guardada.id != orden.id) {
          // La orden creada sin conexión recibió su id definitivo del servidor
          for (final id in orden.detalleIds) {
            await _detallesBox.delete(id);
          }
          await _ordenesBox.delete(orden.id);
        }
        resultado = guardada;
        logd('[orden ${guardada.id}] guardada localmente: ${_resumen(detallesDe(guardada))}');
      } catch (e) {
        // Si el servidor respondió con un rechazo (turno cerrado, validación,
        // orden ya cerrada...) reintentar no sirve: se informa al usuario en
        // lugar de dejar una venta pendiente que nunca se sincronizará.
        if (e is ApiException &&
            e.statusCode != null &&
            e.statusCode! >= 400 &&
            e.statusCode! < 500) {
          rethrow;
        }
        logd('No se pudo enviar la orden al servidor, queda pendiente: $e');
        resultado = orden.copyWith(isLocal: true);
      }
    } else {
      resultado = orden.copyWith(isLocal: true);
    }

    await _ordenesBox.put(resultado.id, resultado);
    if (resultado.isLocal && resultado.estado != 'abierta') {
      await _syncService.addToSyncQueue(resultado);
    }
    _currentOrden = resultado.estado == 'abierta' ? resultado : _currentOrden;
    return resultado;
  }

  Map<String, dynamic> _payload(Orden o, List<DetalleOrden> detalles) {
    return {
      'estado': o.estado,
      if (o.metodoPago != null) 'metodo_pago': o.metodoPago,
      'subtotal': o.subtotal,
      'impuesto': o.impuesto,
      'total': o.total,
      'aplica_iva': o.aplicaIva,
      'tasa_cambio': o.tasaCambio,
      'tipo': o.tipo,
      'cliente_nombre': o.clienteNombre,
      if (int.tryParse(o.clienteId) != null) 'cliente_id': int.parse(o.clienteId),
      'fecha_hora': o.fechaHora.toIso8601String(),
      if (o.notas != null) 'notas': o.notas,
      'detalles': detalles
          .map((d) => {
                'producto_id': int.tryParse(d.productoId) ?? d.productoId,
                'producto_nombre': d.productoNombre,
                'precio_unitario': d.precioUnitario,
                'cantidad': d.cantidad,
              })
          .toList(),
    };
  }

  /// Guarda en Hive una orden tal como la devuelve el servidor (con sus
  /// detalles) y la devuelve como [Orden]. Con [conservar] se mantienen los
  /// datos locales que el servidor no devuelve.
  Future<Orden> _guardarDesdeServidor(
    Map<String, dynamic> json, {
    Orden? conservar,
  }) async {
    final base = Orden.fromJson(json);
    final previa = _ordenesBox.get(base.id) ?? conservar;

    final detallesJson = json['detalles'];
    var detalleIds = previa?.detalleIds ?? <String>[];
    var subtotal = base.subtotal;

    if (detallesJson is List) {
      for (final id in detalleIds) {
        await _detallesBox.delete(id);
      }
      detalleIds = [];
      var suma = 0.0;
      for (final d in detallesJson.whereType<Map>()) {
        final detalle =
            DetalleOrden.fromServer(Map<String, dynamic>.from(d), ordenId: base.id);
        await _detallesBox.put(detalle.id, detalle);
        detalleIds.add(detalle.id);
        suma += detalle.subtotal;
      }
      if (subtotal <= 0) subtotal = _redondear(suma);
    }

    final orden = base.copyWith(
      detalleIds: detalleIds,
      subtotal: subtotal,
      total: base.total > 0 ? base.total : subtotal + base.impuesto,
      // El servidor no devuelve las notas: se conservan las locales
      notas: base.notas ?? conservar?.notas,
      clienteId: base.clienteId.isEmpty ? previa?.clienteId : base.clienteId,
      tasaCambio: base.tasaCambio > 0 ? base.tasaCambio : previa?.tasaCambio,
      syncedAt: DateTime.now(),
      isLocal: false,
    );
    await _ordenesBox.put(orden.id, orden);
    return orden;
  }

  Future<_Impresion> _imprimirComanda(
    Orden orden,
    List<DetalleOrden> actuales,
    ComandaChange cambios,
    bool yaEnviada,
  ) async {
    if (!yaEnviada) {
      if (actuales.isEmpty) return const _Impresion();
      return _imprimir(() => _bluetoothService.printKitchenCommand(orden, actuales));
    }
    if (!cambios.tieneCambios) return const _Impresion();

    final d = _diferencias(cambios, actuales);
    final anadidos = d.anadidos;
    final eliminados = d.eliminados;

    _Impresion resultado = const _Impresion();
    if (anadidos.isNotEmpty) {
      resultado = await _imprimir(
          () => _bluetoothService.printProductosAnadidos(orden, anadidos));
    }
    if (eliminados.isNotEmpty && (resultado.ok || resultado.simulada)) {
      final r = await _imprimir(
          () => _bluetoothService.printProductosEliminados(orden, eliminados));
      if (!r.ok) resultado = r.simulada && resultado.simulada ? resultado : r;
    }
    return resultado;
  }

  DetalleOrden _conCantidad(List<DetalleOrden> actuales, String productoId, double cantidad) {
    final base = actuales.firstWhere((d) => d.productoId == productoId);
    return DetalleOrden(
      id: base.id,
      ordenId: base.ordenId,
      productoId: base.productoId,
      productoNombre: base.productoNombre,
      precioUnitario: base.precioUnitario,
      cantidad: cantidad,
      notas: base.notas,
    );
  }

  /// Lo que se mostraría en la vista previa de una comanda con cambios (Web):
  /// los mismos productos que se imprimirían (añadidos, más unidades, y los
  /// eliminados o reducidos marcados como tales).
  List<DetalleOrden> _soloCambios(ComandaChange cambios, List<DetalleOrden> actuales) {
    final d = _diferencias(cambios, actuales);
    return [
      ...d.anadidos,
      ...d.eliminados.map((e) => DetalleOrden(
            id: e.id,
            ordenId: e.ordenId,
            productoId: e.productoId,
            productoNombre: 'ELIMINAR: ${e.productoNombre}',
            precioUnitario: e.precioUnitario,
            cantidad: e.cantidad,
            notas: e.notas,
          )),
    ];
  }

  ({List<DetalleOrden> anadidos, List<DetalleOrden> eliminados}) _diferencias(
    ComandaChange cambios,
    List<DetalleOrden> actuales,
  ) {
    final anadidos = <DetalleOrden>[
      ...cambios.detallesAgregados,
      // Más unidades de un producto ya enviado: solo se imprime la diferencia
      ...cambios.detallesModificados
          .where((c) => c.diferencia > 0)
          .map((c) => _conCantidad(actuales, c.productoId, c.diferencia)),
    ];
    final eliminados = <DetalleOrden>[
      ...cambios.detallesEliminados,
      ...cambios.detallesModificados
          .where((c) => c.diferencia < 0)
          .map((c) => _conCantidad(actuales, c.productoId, -c.diferencia)),
    ];
    return (anadidos: anadidos, eliminados: eliminados);
  }

  Future<_Impresion> _imprimir(Future<void> Function() trabajo) async {
    try {
      await trabajo();
      return const _Impresion();
    } catch (e) {
      final texto = e.toString();
      if (texto.contains('WEB_SIMULATION')) return const _Impresion(simulada: true);
      logd('Error de impresión: $texto');
      return _Impresion(error: texto.replaceFirst('Exception: ', ''));
    }
  }

  static const _kComandasImpresas = 'comandas_impresas';

  Future<bool> _comandaImpresa(String ordenId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return (prefs.getStringList(_kComandasImpresas) ?? const []).contains(ordenId);
    } catch (_) {
      return true; // ante la duda, no reimprimir todo
    }
  }

  Future<void> _marcarComandaImpresa(String ordenId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final ids = prefs.getStringList(_kComandasImpresas) ?? <String>[];
      if (!ids.contains(ordenId)) {
        ids.add(ordenId);
        // Solo interesan las órdenes recientes
        await prefs.setStringList(
            _kComandasImpresas, ids.length > 300 ? ids.sublist(ids.length - 300) : ids);
      }
    } catch (e) {
      logd('No se pudo guardar la marca de comanda impresa: $e');
    }
  }

  String _resumen(List<DetalleOrden> ds) =>
      ds.map((d) => '${d.productoNombre} x${formatCantidad(d.cantidad)}').join(', ');

  double _redondear(double v) => (v * 100).roundToDouble() / 100;
}
