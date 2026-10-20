import 'package:flutter_test/flutter_test.dart';
import 'package:savecoin_pos/models/detalle_orden.dart';
import 'package:savecoin_pos/services/comanda_change_service.dart';

DetalleOrden d(String productoId, double cantidad) => DetalleOrden(
      ordenId: 'o1',
      productoId: productoId,
      productoNombre: 'P$productoId',
      precioUnitario: 1,
      cantidad: cantidad,
    );

void main() {
  final service = ComandaChangeService();

  test('sin cambios', () {
    final r = service.detectarCambios([d('1', 2)], [d('1', 2)], 'ORD-1');
    expect(r.tieneCambios, isFalse);
  });

  test('detecta agregados, eliminados y cambios de cantidad', () {
    final r = service.detectarCambios(
      [d('1', 2), d('2', 1)],
      [d('1', 3), d('3', 1)],
      'ORD-1',
    );

    expect(r.detallesAgregados.map((e) => e.productoId), ['3']);
    expect(r.detallesEliminados.map((e) => e.productoId), ['2']);
    expect(r.detallesModificados.single.diferencia, 1);
  });
}
