import 'package:flutter_test/flutter_test.dart';
import 'package:savecoin_pos/models/detalle_orden.dart';
import 'package:savecoin_pos/models/producto.dart';
import 'package:savecoin_pos/providers/cart_provider.dart';
import 'package:savecoin_pos/utils/constants.dart';

Producto producto(String id, double precio) =>
    Producto(id: id, nombre: 'Producto $id', codigo: 'P$id', precioVenta: precio);

void main() {
  test('suma cantidades del mismo producto y calcula el subtotal', () {
    final cart = CartProvider();
    cart.addItem(producto('1', 10));
    cart.addItem(producto('1', 10));
    cart.addItem(producto('2', 5));

    expect(cart.items.length, 2);
    expect(cart.itemCount, 3);
    expect(cart.subtotal, 25);
    expect(cart.impuesto, 0);
    expect(cart.total, 25);
  });

  test('el IVA solo se aplica cuando se activa', () {
    final cart = CartProvider();
    cart.addItem(producto('1', 100));
    cart.setAplicaIva(true);

    expect(cart.impuesto, closeTo(100 * kIvaRate, 0.001));
    expect(cart.total, closeTo(100 * (1 + kIvaRate), 0.001));
  });

  test('cantidad 0 elimina el producto', () {
    final cart = CartProvider();
    cart.addItem(producto('1', 10));
    cart.updateCantidad('1', 0);
    expect(cart.items, isEmpty);
  });

  test('loadFromDetallesOrden no pierde productos que ya no están en el catálogo', () {
    final cart = CartProvider();
    final detalles = [
      DetalleOrden(
          ordenId: 'o1', productoId: '1', productoNombre: 'Café', precioUnitario: 3, cantidad: 2),
      DetalleOrden(
          ordenId: 'o1', productoId: '9', productoNombre: 'Descontinuado', precioUnitario: 7, cantidad: 1),
    ];

    cart.loadFromDetallesOrden(detalles, {'1': producto('1', 3)});

    expect(cart.items.length, 2);
    expect(cart.itemCount, 3);
    expect(cart.subtotal, 13);
  });
}
