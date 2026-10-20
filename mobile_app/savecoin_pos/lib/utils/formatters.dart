/// Formatea una fecha como `dd/MM/yyyy HH:mm` (con ceros a la izquierda).
String formatFechaHora(DateTime date) {
  String dos(int n) => n.toString().padLeft(2, '0');
  return '${dos(date.day)}/${dos(date.month)}/${date.year} ${dos(date.hour)}:${dos(date.minute)}';
}

/// Cantidad sin decimales sobrantes: `2.0` → `2`, `1.5` → `1.5`.
String formatCantidad(double cantidad) {
  return cantidad == cantidad.truncateToDouble()
      ? cantidad.toInt().toString()
      : cantidad.toString();
}
