/// Fecha ISO 8601 con la zona horaria del tablet (ej. 2026-11-11T21:35:00-04:00).
/// `toIso8601String()` de una fecha local no trae la zona, y el servidor la interpretaba en otra.
String fechaConZona(DateTime fecha) {
  final local = fecha.toLocal();
  final offset = local.timeZoneOffset;
  final signo = offset.isNegative ? '-' : '+';
  final horas = offset.inHours.abs().toString().padLeft(2, '0');
  final minutos = (offset.inMinutes.abs() % 60).toString().padLeft(2, '0');
  final sinMilisegundos = local.toIso8601String().split('.').first;
  return '$sinMilisegundos$signo$horas:$minutos';
}
