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

  Configuracion({
    this.tasaCambio = 36.5, // Tasa por defecto
    this.printerMacAddress,
    this.printerName,
    this.apiUrl = kDefaultApiUrl,
    this.apiToken = '',
    this.lastSyncAt,
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
    };
  }

  Configuracion copyWith({
    double? tasaCambio,
    String? printerMacAddress,
    String? printerName,
    String? apiUrl,
    String? apiToken,
    DateTime? lastSyncAt,
  }) {
    return Configuracion(
      tasaCambio: tasaCambio ?? this.tasaCambio,
      printerMacAddress: printerMacAddress ?? this.printerMacAddress,
      printerName: printerName ?? this.printerName,
      apiUrl: apiUrl ?? this.apiUrl,
      apiToken: apiToken ?? this.apiToken,
      lastSyncAt: lastSyncAt ?? this.lastSyncAt,
    );
  }
}
