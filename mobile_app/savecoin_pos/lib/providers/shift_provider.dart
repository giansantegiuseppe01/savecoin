import 'package:flutter/foundation.dart';
import '../services/api_service.dart';
import '../utils/log.dart';

class ShiftProvider with ChangeNotifier {
  final ApiService _apiService;
  bool _isShiftOpen = false;
  bool _isLoaded = false;
  bool _isLoading = false;
  String? _shiftId;
  DateTime? _shiftStartTime;

  /// Resumen (ventas por método de pago) del último turno cerrado, para imprimirlo.
  Map<String, dynamic>? lastClosingSummary;

  ShiftProvider(this._apiService);

  bool get isShiftOpen => _isShiftOpen;

  /// true cuando ya se consultó al servidor si hay un turno abierto.
  bool get isLoaded => _isLoaded;
  String? get shiftId => _shiftId;
  DateTime? get shiftStartTime => _shiftStartTime;

  /// Consulta al servidor si el usuario ya tiene un turno abierto. Antes se
  /// perdía el estado al reiniciar la app y había que "abrir" otro turno.
  Future<void> loadStatus() async {
    if (_isLoading) return;
    _isLoading = true;

    try {
      final response = await _apiService.getTurnoActivo();
      final data = response['data'];

      if (data is Map) {
        _isShiftOpen = true;
        _shiftId = data['id']?.toString();
        _shiftStartTime = DateTime.tryParse('${data['hora_apertura']}') ?? DateTime.now();
      } else {
        _isShiftOpen = false;
        _shiftId = null;
        _shiftStartTime = null;
      }
    } catch (e) {
      // Sin conexión: se muestra la pantalla de inicio y se podrá reintentar
      logd('No se pudo consultar el turno activo: $e');
      _isShiftOpen = false;
    } finally {
      _isLoading = false;
      _isLoaded = true;
      notifyListeners();
    }
  }

  /// Olvida el estado del turno (al cerrar sesión o cambiar de usuario).
  void reset() {
    _isShiftOpen = false;
    _isLoaded = false;
    _shiftId = null;
    _shiftStartTime = null;
    notifyListeners();
  }

  Future<bool> openShift() async {
    try {
      final response = await _apiService.abrirTurno();

      // Aceptar tanto 201 (turno nuevo) como 400 (ya existe turno abierto)
      final message = response['message'];
      if (message == 'Turno abierto exitosamente' || message == 'Ya tienes un turno abierto') {
        _isShiftOpen = true;
        _shiftId = response['data']?['id']?.toString();
        _shiftStartTime = DateTime.now();
        notifyListeners();
        return true;
      }
      logd('Error al abrir turno: $message');
      return false;
    } catch (e) {
      logd('Error opening shift: $e');
      return false;
    }
  }

  Future<bool> closeShift() async {
    try {
      final response = await _apiService.cerrarTurno();

      if (response['message'] == 'Turno cerrado exitosamente') {
        final resumen = response['resumen'];
        lastClosingSummary = resumen is Map ? Map<String, dynamic>.from(resumen) : null;
        _isShiftOpen = false;
        _shiftId = null;
        _shiftStartTime = null;
        notifyListeners();
        return true;
      }
      logd('Error al cerrar turno: ${response['message']}');
      return false;
    } catch (e) {
      logd('Error closing shift: $e');
      return false;
    }
  }
}
