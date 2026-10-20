import 'package:hive/hive.dart';
import 'detalle_orden.dart';

part 'orden.g.dart';

@HiveType(typeId: 3)
class Orden extends HiveObject {
  @HiveField(0)
  final String id; // ID del servidor (BIGINT)

  @HiveField(1)
  String numeroRecibo; // Se generará como ORD-{id}

  @HiveField(2)
  String clienteId; // Mantenemos como String para compatibilidad con Hive

  @HiveField(3)
  String clienteNombre;

  @HiveField(4)
  String tipo; // 'venta' o 'reembolso'

  @HiveField(5)
  double subtotal;

  @HiveField(6)
  double descuento;

  @HiveField(7)
  double impuesto;

  @HiveField(8)
  double total;

  @HiveField(9)
  bool aplicaIva; // Indica si se aplica IVA a esta orden

  @HiveField(10)
  double tasaCambio;

  @HiveField(11)
  String estado; // 'abierta', 'cerrada', 'anulada'

  @HiveField(12)
  String? metodoPago;

  @HiveField(13)
  String? turno;

  @HiveField(14)
  DateTime fechaHora;

  @HiveField(15)
  String? notas;

  @HiveField(16)
  DateTime? syncedAt;

  @HiveField(17)
  bool isLocal;

  @HiveField(18)
  List<String> detalleIds; // IDs de DetalleOrden

  Orden({
    String? id,
    required this.numeroRecibo,
    required this.clienteId,
    required this.clienteNombre,
    this.tipo = 'venta',
    this.subtotal = 0,
    this.descuento = 0,
    this.impuesto = 0,
    this.total = 0,
    required this.tasaCambio,
    this.estado = 'abierta',
    this.metodoPago,
    this.turno,
    DateTime? fechaHora,
    this.notas,
    this.syncedAt,
    this.isLocal = true,
    this.aplicaIva = false,
    List<String>? detalleIds,
  })  : id = id ?? DateTime.now().millisecondsSinceEpoch.toString(),
        fechaHora = fechaHora ?? DateTime.now(),
        detalleIds = detalleIds ?? [];

  factory Orden.fromJson(Map<String, dynamic> json) {
    final idValue = json['id'];
    String idParsed = DateTime.now().millisecondsSinceEpoch.toString();

    if (idValue != null) {
      if (idValue is int) {
        idParsed = idValue.toString();
      } else if (idValue is String) {
        idParsed = idValue;
      }
    }

    // Helper para parsear num (puede venir como String o num)
    num parseNum(dynamic value) {
      if (value == null) return 0;
      if (value is num) return value;
      if (value is String) return num.tryParse(value) ?? 0;
      return 0;
    }

    return Orden(
      id: idParsed,
      numeroRecibo: json['numero_recibo'] as String? ?? 'ORD-$idParsed',
      clienteId: json['cliente_id']?.toString() ?? '',
      clienteNombre: json['cliente_nombre'] as String? ?? 'General',
      tipo: json['tipo'] as String? ?? 'venta',
      subtotal: parseNum(json['subtotal']).toDouble(),
      descuento: parseNum(json['descuento']).toDouble(),
      impuesto: parseNum(json['impuesto']).toDouble(),
      total: parseNum(json['total']).toDouble(),
      tasaCambio: parseNum(json['tasa_cambio']).toDouble(),
      estado: json['estado'] as String? ?? 'abierta',
      metodoPago: json['metodo_pago'] as String?,
      turno: json['turno'] as String?,
      fechaHora: json['fecha_hora'] != null
          ? DateTime.parse(json['fecha_hora'] as String)
          : DateTime.now(),
      notas: json['notas'] as String?,
      syncedAt: json['synced_at'] != null
          ? DateTime.parse(json['synced_at'] as String)
          : null,
      isLocal: false,
      aplicaIva: json['aplica_iva'] as bool? ?? false,
      detalleIds: _parseDetalleIds(json),
    );
  }

  /// IDs de los detalles: `detalle_ids` (guardado local) o, si no existe, los
  /// `detalles` completos que devuelve el servidor.
  static List<String> _parseDetalleIds(Map<String, dynamic> json) {
    final ids = json['detalle_ids'];
    if (ids is List) {
      return ids.map((e) => e.toString()).toList();
    }

    final detalles = json['detalles'];
    if (detalles is List) {
      return detalles
          .whereType<Map>()
          .map((d) => d['id']?.toString())
          .whereType<String>()
          .toList();
    }

    return [];
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'numero_recibo': numeroRecibo,
      'cliente_id': clienteId,
      'cliente_nombre': clienteNombre,
      'tipo': tipo,
      'subtotal': subtotal,
      'descuento': descuento,
      'impuesto': impuesto,
      'total': total,
      'tasa_cambio': tasaCambio,
      'estado': estado,
      'metodo_pago': metodoPago,
      'turno': turno,
      'fecha_hora': fechaHora.toIso8601String(),
      'notas': notas,
      'is_local': isLocal,
      'aplica_iva': aplicaIva,
      'detalle_ids': detalleIds,
      'detalles': _detallesToMap(), // Enviar detalles completos para deducción de inventario
    };
  }

  // Método helper para convertir detalles de Hive a mapa
  List<Map<String, dynamic>> _detallesToMap() {
    if (detalleIds.isEmpty) return [];
    
    final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
    return detalleIds.map((id) {
      final detalle = detallesBox.get(id);
      if (detalle == null) return null;
      return {
        'producto_id': detalle.productoId,
        'producto_nombre': detalle.productoNombre,
        'precio_unitario': detalle.precioUnitario,
        'cantidad': detalle.cantidad,
        'subtotal': detalle.subtotal,
      };
    }).whereType<Map<String, dynamic>>().toList();
  }

  double get totalEnBs => total * tasaCambio;

  Orden copyWith({
    String? numeroRecibo,
    String? clienteId,
    String? clienteNombre,
    String? tipo,
    double? subtotal,
    double? descuento,
    double? impuesto,
    double? total,
    double? tasaCambio,
    String? estado,
    String? metodoPago,
    String? turno,
    DateTime? fechaHora,
    String? notas,
    bool clearNotas = false, // true = dejar las notas en null (con `notas: null` no se puede)
    DateTime? syncedAt,
    bool? isLocal,
    bool? aplicaIva,
    List<String>? detalleIds,
  }) {
    return Orden(
      id: id,
      numeroRecibo: numeroRecibo ?? this.numeroRecibo,
      clienteId: clienteId ?? this.clienteId,
      clienteNombre: clienteNombre ?? this.clienteNombre,
      tipo: tipo ?? this.tipo,
      subtotal: subtotal ?? this.subtotal,
      descuento: descuento ?? this.descuento,
      impuesto: impuesto ?? this.impuesto,
      total: total ?? this.total,
      tasaCambio: tasaCambio ?? this.tasaCambio,
      estado: estado ?? this.estado,
      metodoPago: metodoPago ?? this.metodoPago,
      turno: turno ?? this.turno,
      fechaHora: fechaHora ?? this.fechaHora,
      notas: clearNotas ? null : (notas ?? this.notas),
      syncedAt: syncedAt ?? this.syncedAt,
      isLocal: isLocal ?? this.isLocal,
      aplicaIva: aplicaIva ?? this.aplicaIva,
      detalleIds: detalleIds ?? this.detalleIds,
    );
  }
}
