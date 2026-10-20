import 'package:hive/hive.dart';
import 'package:uuid/uuid.dart';

part 'detalle_orden.g.dart';

@HiveType(typeId: 4)
class DetalleOrden extends HiveObject {
  @HiveField(0)
  final String id;
  
  @HiveField(1)
  String ordenId;
  
  @HiveField(2)
  String productoId;
  
  @HiveField(3)
  String productoNombre;
  
  @HiveField(4)
  double precioUnitario;
  
  @HiveField(5)
  double cantidad;
  
  @HiveField(6)
  double subtotal;
  
  @HiveField(7)
  String? notas;
  
  @HiveField(8)
  DateTime? syncedAt;
  
  @HiveField(9)
  bool isLocal;
  
  @HiveField(10)
  bool isNew; // Para imprimir solo items nuevos al actualizar comanda
  
  @HiveField(11)
  bool fueModificado; // Indica si este detalle fue modificado desde su creación

  DetalleOrden({
    String? id,
    required this.ordenId,
    required this.productoId,
    required this.productoNombre,
    required this.precioUnitario,
    required this.cantidad,
    this.notas,
    this.syncedAt,
    this.isLocal = true,
    this.isNew = true,
    this.fueModificado = false,
  })  : id = id ?? const Uuid().v4(),
        subtotal = precioUnitario * cantidad;

  factory DetalleOrden.fromJson(Map<String, dynamic> json) {
    return DetalleOrden(
      id: json['id']?.toString() ?? const Uuid().v4(),
      ordenId: json['orden_id'].toString(),
      productoId: json['producto_id'].toString(),
      productoNombre: json['producto_nombre'] as String? ?? '',
      precioUnitario: double.tryParse(json['precio_unitario'].toString()) ?? 0.0,
      cantidad: double.tryParse(json['cantidad'].toString()) ?? 0.0,
      notas: json['notas'] as String?,
      syncedAt: json['synced_at'] != null 
          ? DateTime.parse(json['synced_at'] as String) 
          : null,
      isLocal: false,
      isNew: false,
      fueModificado: json['fue_modificado'] as bool? ?? false,
    );
  }

  /// Crea un detalle a partir de lo que devuelve el servidor (los ids y los
  /// números pueden venir como int, double o String).
  factory DetalleOrden.fromServer(Map<String, dynamic> json, {required String ordenId}) {
    double num0(dynamic v) {
      if (v is num) return v.toDouble();
      if (v is String) return double.tryParse(v) ?? 0.0;
      return 0.0;
    }

    return DetalleOrden(
      id: json['id']?.toString(),
      ordenId: ordenId,
      productoId: json['producto_id'].toString(),
      productoNombre: json['producto_nombre'] as String? ?? '',
      precioUnitario: num0(json['precio_unitario']),
      cantidad: num0(json['cantidad']),
      notas: json['notas'] as String?,
      isLocal: false,
      isNew: false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'orden_id': ordenId,
      'producto_id': productoId,
      'producto_nombre': productoNombre,
      'precio_unitario': precioUnitario,
      'cantidad': cantidad,
      'subtotal': subtotal,
      'notas': notas,
      'is_local': isLocal,
      'is_new': isNew,
      'fue_modificado': fueModificado,
    };
  }

  DetalleOrden copyWith({
    String? ordenId,
    String? productoId,
    String? productoNombre,
    double? precioUnitario,
    double? cantidad,
    String? notas,
    DateTime? syncedAt,
    bool? isLocal,
    bool? isNew,
    bool? fueModificado,
  }) {
    return DetalleOrden(
      id: id,
      ordenId: ordenId ?? this.ordenId,
      productoId: productoId ?? this.productoId,
      productoNombre: productoNombre ?? this.productoNombre,
      precioUnitario: precioUnitario ?? this.precioUnitario,
      cantidad: cantidad ?? this.cantidad,
      notas: notas ?? this.notas,
      syncedAt: syncedAt ?? this.syncedAt,
      isLocal: isLocal ?? this.isLocal,
      isNew: isNew ?? this.isNew,
      fueModificado: fueModificado ?? this.fueModificado,
    );
  }
}
