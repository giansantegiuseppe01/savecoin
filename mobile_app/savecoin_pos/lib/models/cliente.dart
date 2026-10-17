import 'package:hive/hive.dart';
import 'package:uuid/uuid.dart';

part 'cliente.g.dart';

@HiveType(typeId: 2)
class Cliente extends HiveObject {
  @HiveField(0)
  final String id;
  
  @HiveField(1)
  String nombre;
  
  @HiveField(2)
  String apellido;
  
  @HiveField(3)
  String cedula;
  
  @HiveField(4)
  String? telefono;
  
  @HiveField(5)
  String? correo;
  
  @HiveField(6)
  DateTime? syncedAt;
  
  @HiveField(7)
  bool isLocal;

  Cliente({
    String? id,
    required this.nombre,
    required this.apellido,
    required this.cedula,
    this.telefono,
    this.correo,
    this.syncedAt,
    this.isLocal = false,
  }) : id = id ?? const Uuid().v4();

factory Cliente.fromJson(Map<String, dynamic> json) {
  return Cliente(
    // Convierte el ID entero (1) a String ("1")
    id: json['id']?.toString() ?? const Uuid().v4(),
    nombre: json['nombre']?.toString() ?? '',
    apellido: json['apellido']?.toString() ?? '',
    cedula: (json['cedula'] ?? json['cedula_rif'])?.toString() ?? '',
    telefono: json['telefono']?.toString(),
    // Lee tanto 'correo' como 'email' por si Laravel usa la convención estándar
    correo: (json['correo'] ?? json['email'])?.toString(),
    syncedAt: json['synced_at'] != null 
        ? DateTime.parse(json['synced_at'].toString()) 
        : DateTime.now(),
    isLocal: false,
  );
}

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombre': nombre,
      'apellido': apellido,
      'cedula': cedula,
      'telefono': telefono,
      'correo': correo,
      'is_local': isLocal,
    };
  }

  String get nombreCompleto => '$nombre $apellido';

  Cliente copyWith({
    String? nombre,
    String? apellido,
    String? cedula,
    String? telefono,
    String? correo,
    DateTime? syncedAt,
    bool? isLocal,
  }) {
    return Cliente(
      id: id,
      nombre: nombre ?? this.nombre,
      apellido: apellido ?? this.apellido,
      cedula: cedula ?? this.cedula,
      telefono: telefono ?? this.telefono,
      correo: correo ?? this.correo,
      syncedAt: syncedAt ?? this.syncedAt,
      isLocal: isLocal ?? this.isLocal,
    );
  }
}
