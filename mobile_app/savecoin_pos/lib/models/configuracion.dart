import 'dart:convert';
import 'package:hive/hive.dart';
import '../utils/constants.dart';

part 'configuracion.g.dart';

@HiveType(typeId: 5)
class Configuracion extends HiveObject {
  @HiveField(0)
  double tasaCambio; // USD a VES
  
  @HiveField(1)
  String? printerMacAddress;
  
  @HiveField(2)
  String? printerName;
  
  @HiveField(3)
  String apiUrl;
  
  // OBSOLETO: el token de sesión vive en TokenStorage. El campo se conserva
  // solo para no romper los datos ya guardados en Hive.
  @HiveField(4)
  String apiToken;
  
  @HiveField(5)
  DateTime? lastSyncAt;

  // Datos de la empresa para el encabezado de los tickets
  @HiveField(6)
  String? nombreEmpresa;

  @HiveField(7)
  String? rif;

  @HiveField(8)
  String? telefono;

  @HiveField(9)
  String? direccion;

  @HiveField(10)
  String? logoUrl;

  /// Logo descargado (PNG en base64) para imprimirlo sin conexión.
  @HiveField(11)
  String? logoBase64;

  /// Formas de pago activas: JSON `[{"nombre": ..., "datos": ...}]`.
  @HiveField(12)
  String? formasPagoJson;

  List<({String nombre, String datos})> get formasPago {
    final raw = formasPagoJson;
    if (raw == null || raw.isEmpty) return const [];
    try {
      return [
        for (final f in (jsonDecode(raw) as List))
          (nombre: '${f['nombre']}', datos: '${f['datos']}'),
      ];
    } catch (_) {
      return const [];
    }
  }

  Configuracion({
    this.tasaCambio = 36.5, // Tasa por defecto
    this.printerMacAddress,
    this.printerName,
    this.apiUrl = kDefaultApiUrl,
    this.apiToken = '',
    this.lastSyncAt,
    this.nombreEmpresa,
    this.rif,
    this.telefono,
    this.direccion,
    this.logoUrl,
    this.logoBase64,
    this.formasPagoJson,
  });

  factory Configuracion.fromJson(Map<String, dynamic> json) {
    final tasaCambioValue = json['tasa_cambio'];
    double tasaCambioParsed = 36.5;
    
    if (tasaCambioValue != null) {
      if (tasaCambioValue is num) {
        tasaCambioParsed = tasaCambioValue.toDouble();
      } else if (tasaCambioValue is String) {
        tasaCambioParsed = double.tryParse(tasaCambioValue) ?? 36.5;
      }
    }
    
    return Configuracion(
      tasaCambio: tasaCambioParsed,
      printerMacAddress: json['printer_mac_address'] as String?,
      printerName: json['printer_name'] as String?,
      apiUrl: json['api_url'] as String? ?? kDefaultApiUrl,
      apiToken: json['api_token'] as String? ?? '',
      lastSyncAt: json['last_sync_at'] != null 
          ? DateTime.parse(json['last_sync_at'] as String) 
          : null,
      nombreEmpresa: json['nombre_empresa'] as String?,
      rif: json['rif'] as String?,
      telefono: json['telefono'] as String?,
      direccion: json['direccion'] as String?,
      logoUrl: json['logo_url'] as String?,
      formasPagoJson: json['formas_pago'] is List ? jsonEncode(json['formas_pago']) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'tasa_cambio': tasaCambio,
      'printer_mac_address': printerMacAddress,
      'printer_name': printerName,
      'api_url': apiUrl,
      'api_token': apiToken,
      'last_sync_at': lastSyncAt?.toIso8601String(),
      'nombre_empresa': nombreEmpresa,
      'rif': rif,
      'telefono': telefono,
      'direccion': direccion,
      'logo_url': logoUrl,
      'formas_pago': formasPagoJson == null ? null : jsonDecode(formasPagoJson!),
    };
  }

  Configuracion copyWith({
    double? tasaCambio,
    String? printerMacAddress,
    String? printerName,
    String? apiUrl,
    String? apiToken,
    DateTime? lastSyncAt,
    String? nombreEmpresa,
    String? rif,
    String? telefono,
    String? direccion,
    String? logoUrl,
    String? logoBase64,
    String? formasPagoJson,
  }) {
    return Configuracion(
      tasaCambio: tasaCambio ?? this.tasaCambio,
      printerMacAddress: printerMacAddress ?? this.printerMacAddress,
      printerName: printerName ?? this.printerName,
      apiUrl: apiUrl ?? this.apiUrl,
      apiToken: apiToken ?? this.apiToken,
      lastSyncAt: lastSyncAt ?? this.lastSyncAt,
      nombreEmpresa: nombreEmpresa ?? this.nombreEmpresa,
      rif: rif ?? this.rif,
      telefono: telefono ?? this.telefono,
      direccion: direccion ?? this.direccion,
      logoUrl: logoUrl ?? this.logoUrl,
      logoBase64: logoBase64 ?? this.logoBase64,
      formasPagoJson: formasPagoJson ?? this.formasPagoJson,
    );
  }
}
