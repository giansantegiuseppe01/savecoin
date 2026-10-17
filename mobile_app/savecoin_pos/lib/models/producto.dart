import 'package:hive/hive.dart';
import 'package:uuid/uuid.dart';

part 'producto.g.dart';

@HiveType(typeId: 1)
class Producto extends HiveObject {
  @HiveField(0)
  final String id;
  
  @HiveField(1)
  String nombre;
  
  @HiveField(2)
  String codigo;
  
  @HiveField(3)
  String? descripcion;
  
  @HiveField(4)
  double precioVenta;
  
  @HiveField(5)
  String? categoria;
  
  @HiveField(6)
  String? imagenUrl;
  
  @HiveField(7)
  bool activo;
  
  @HiveField(8)
  bool preparadoEnCocina;
  
  @HiveField(9)
  int? tiempoPreparacionMinutos;
  
  @HiveField(10)
  DateTime? syncedAt;
  
  @HiveField(11)
  bool isLocal; // Created offline

  Producto({
    String? id,
    required this.nombre,
    required this.codigo,
    this.descripcion,
    required this.precioVenta,
    this.categoria,
    this.imagenUrl,
    this.activo = true,
    this.preparadoEnCocina = false,
    this.tiempoPreparacionMinutos,
    this.syncedAt,
    this.isLocal = false,
  }) : id = id ?? const Uuid().v4();

factory Producto.fromJson(Map<String, dynamic> json) {
  return Producto(
    // Convierte 1 int -> "1" String
    id: json['id']?.toString() ?? const Uuid().v4(), 
    nombre: json['nombre'] as String? ?? '',
    codigo: json['codigo'] as String? ?? '',
    descripcion: json['descripcion'] as String?,
    // Convierte "6.0000" String o num -> double 6.0
    precioVenta: double.tryParse(json['precio_venta']?.toString() ?? '0') ?? 0.0, 
    // Si viene objeto categoria toma el nombre, si es null queda null
    categoria: json['categoria'] is Map 
        ? json['categoria']['nombre']?.toString() 
        : json['categoria']?.toString(), 
    imagenUrl: json['imagen_url'] as String?,
    activo: json['activo'] as bool? ?? true,
    preparadoEnCocina: json['preparado_en_cocina'] as bool? ?? false,
    tiempoPreparacionMinutos: json['tiempo_preparacion_minutos'] as int?,
    syncedAt: json['synced_at'] != null 
        ? DateTime.parse(json['synced_at'] as String) 
        : DateTime.now(), // Marca la hora de la descarga
    isLocal: false,
  );
}

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombre': nombre,
      'codigo': codigo,
      'descripcion': descripcion,
      'precio_venta': precioVenta,
      'categoria': categoria,
      'imagen_url': imagenUrl,
      'activo': activo,
      'preparado_en_cocina': preparadoEnCocina,
      'tiempo_preparacion_minutos': tiempoPreparacionMinutos,
      'is_local': isLocal,
    };
  }

  Producto copyWith({
    String? nombre,
    String? codigo,
    String? descripcion,
    double? precioVenta,
    String? categoria,
    String? imagenUrl,
    bool? activo,
    bool? preparadoEnCocina,
    int? tiempoPreparacionMinutos,
    DateTime? syncedAt,
    bool? isLocal,
  }) {
    return Producto(
      id: id,
      nombre: nombre ?? this.nombre,
      codigo: codigo ?? this.codigo,
      descripcion: descripcion ?? this.descripcion,
      precioVenta: precioVenta ?? this.precioVenta,
      categoria: categoria ?? this.categoria,
      imagenUrl: imagenUrl ?? this.imagenUrl,
      activo: activo ?? this.activo,
      preparadoEnCocina: preparadoEnCocina ?? this.preparadoEnCocina,
      tiempoPreparacionMinutos: tiempoPreparacionMinutos ?? this.tiempoPreparacionMinutos,
      syncedAt: syncedAt ?? this.syncedAt,
      isLocal: isLocal ?? this.isLocal,
    );
  }
}
