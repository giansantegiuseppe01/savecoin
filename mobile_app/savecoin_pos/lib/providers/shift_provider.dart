import 'package:flutter/foundation.dart';
import '../services/api_service.dart';

class ShiftProvider with ChangeNotifier {
  final ApiService _apiService;
  bool _isShiftOpen = false;
  String? _shiftId;
  DateTime? _shiftStartTime;

  ShiftProvider(this._apiService) {
    _loadShiftStatus();
  }

  bool get isShiftOpen => _isShiftOpen;
  String? get shiftId => _shiftId;
  DateTime? get shiftStartTime => _shiftStartTime;

  Future<void> _loadShiftStatus() async {
    // For web simulation, check local storage
    if (kIsWeb) {
      // In a real app, this would check the server
      _isShiftOpen = false;
      notifyListeners();
    }
  }

  Future<bool> openShift() async {
    try {
      print('openShift llamado');

      final response = await _apiService.abrirTurno();
      print('openShift response: $response');

      // Aceptar tanto 201 (turno nuevo) como 400 (ya existe turno abierto)
      if (response['message'] == 'Turno abierto exitosamente' || response['message'] == 'Ya tienes un turno abierto') {
        _isShiftOpen = true;
        _shiftId = response['data']['id'].toString();
        _shiftStartTime = DateTime.now();
        notifyListeners();
        print('Turno abierto exitosamente');
        return true;
      }
      print('Error al abrir turno: ${response['message']}');
      return false;
    } catch (e) {
      print('Error opening shift: $e');
      return false;
    }
  }

  Future<bool> closeShift() async {
    try {
      print('closeShift llamado');

      final response = await _apiService.cerrarTurno();
      print('closeShift response: $response');

      if (response['message'] == 'Turno cerrado exitosamente') {
        _isShiftOpen = false;
        _shiftId = null;
        _shiftStartTime = null;
        notifyListeners();
        print('Turno cerrado exitosamente');
        return true;
      }
      print('Error al cerrar turno: ${response['message']}');
      return false;
    } catch (e) {
      print('Error closing shift: $e');
      return false;
    }
  }
}
