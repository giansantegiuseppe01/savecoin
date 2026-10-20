import 'package:flutter/material.dart';

/// Llave global del Navigator, para poder volver a la raíz desde fuera de un
/// widget (por ejemplo cuando el servidor responde 401 y se cierra la sesión).
final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();
