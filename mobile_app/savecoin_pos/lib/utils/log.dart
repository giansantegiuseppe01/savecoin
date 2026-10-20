import 'package:flutter/foundation.dart';

/// Log de depuración: solo imprime en modo debug.
///
/// Reemplaza a `print`, que en release deja en el logcat tokens, cédulas,
/// órdenes y respuestas completas del servidor.
void logd(Object? message) {
  if (kDebugMode) {
    debugPrint('$message');
  }
}
