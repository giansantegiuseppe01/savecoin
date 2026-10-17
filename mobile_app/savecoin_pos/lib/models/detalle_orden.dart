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
      id: json['id'] as String? ?? const Uuid().v4(),
      ordenId: json['orden_id'] as String,
      productoId: json['producto_id'] as String,
      productoNombre: json['producto_nombre'] as String,
      precioUnitario: (json['precio_unitario'] as num).toDouble(),
      cantidad: (json['cantidad'] as num).toDouble(),
      notas: json['notas'] as String?,
      syncedAt: json['synced_at'] != null 
          ? DateTime.parse(json['synced_at'] as String) 
          : null,
      isLocal: false,
      isNew: false,
      fueModificado: json['fue_modificado'] as bool? ?? false,
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
