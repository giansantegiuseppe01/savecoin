import 'package:flutter_test/flutter_test.dart';
import 'package:savecoin_pos/utils/formatters.dart';

void main() {
  test('formatFechaHora rellena con ceros', () {
    expect(formatFechaHora(DateTime(2026, 3, 5, 9, 7)), '05/03/2026 09:07');
  });

  test('formatCantidad quita los decimales sobrantes', () {
    expect(formatCantidad(2), '2');
    expect(formatCantidad(1.5), '1.5');
  });
}
