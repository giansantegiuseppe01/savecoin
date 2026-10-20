import 'package:flutter/foundation.dart';
import '../models/producto.dart';
import '../models/detalle_orden.dart';
import '../utils/constants.dart';

class CartItem {
  final Producto producto;
  int cantidad;
  String? notas;

  CartItem({
    required this.producto,
    this.cantidad = 1,
    this.notas,
  });

  double get subtotal => producto.precioVenta * cantidad;
}

class CartProvider with ChangeNotifier {
  final List<CartItem> _items = [];
  bool _aplicaIva = false;

  List<CartItem> get items => _items;
  int get itemCount => _items.fold(0, (sum, item) => sum + item.cantidad);
  double get subtotal => _items.fold(0.0, (sum, item) => sum + item.subtotal);
  bool get aplicaIva => _aplicaIva;
  double get impuesto => _aplicaIva ? subtotal * kIvaRate : 0.0; // IVA solo si se selecciona manualmente
  double get total => subtotal + impuesto;

  void addItem(Producto producto, {int cantidad = 1, String? notas}) {
    final existingIndex = _items.indexWhere((item) => item.producto.id == producto.id);
    
    if (existingIndex != -1) {
      _items[existingIndex].cantidad += cantidad;
      if (notas != null) {
        _items[existingIndex].notas = notas;
      }
    } else {
      _items.add(CartItem(producto: producto, cantidad: cantidad, notas: notas));
    }
    
    notifyListeners();
  }

  void updateCantidad(String productoId, int cantidad) {
    if (cantidad <= 0) {
      removeItem(productoId);
      return;
    }

    final index = _items.indexWhere((item) => item.producto.id == productoId);
    if (index != -1) {
      _items[index].cantidad = cantidad;
      notifyListeners();
    }
  }

  void updateNotas(String productoId, String? notas) {
    final index = _items.indexWhere((item) => item.producto.id == productoId);
    if (index != -1) {
      _items[index].notas = notas;
      notifyListeners();
    }
  }

  void removeItem(String productoId) {
    _items.removeWhere((item) => item.producto.id == productoId);
    notifyListeners();
  }

  void clear() {
    _items.clear();
    _aplicaIva = false;
    notifyListeners();
  }

  void setAplicaIva(bool value) {
    _aplicaIva = value;
    notifyListeners();
  }

  List<DetalleOrden> toDetallesOrden(String ordenId) {
    return _items.map((item) {
      return DetalleOrden(
        ordenId: ordenId,
        productoId: item.producto.id,
        productoNombre: item.producto.nombre,
        precioUnitario: item.producto.precioVenta,
        cantidad: item.cantidad.toDouble(),
        notas: item.notas,
        isNew: true, // Marcar como nuevo para impresión de comanda
      );
    }).toList();
  }

  /// Carga en el carrito los detalles de una orden existente.
  ///
  /// Si un producto ya no está en el catálogo local se reconstruye a partir del
  /// detalle: antes se omitía y, al guardar la orden, ese producto desaparecía.
  void loadFromDetallesOrden(
    List<DetalleOrden> detalles,
    Map<String, Producto> productosMap, {
    bool aplicaIva = false,
  }) {
    _items.clear();
    _aplicaIva = aplicaIva;

    // Consolidar detalles del mismo producto
    final consolidados = <String, CartItem>{};
    for (final detalle in detalles) {
      final existente = consolidados[detalle.productoId];
      if (existente != null) {
        existente.cantidad += detalle.cantidad.round();
        continue;
      }
      final producto = productosMap[detalle.productoId] ??
          Producto(
            id: detalle.productoId,
            nombre: detalle.productoNombre,
            codigo: '',
            precioVenta: detalle.precioUnitario,
          );
      consolidados[detalle.productoId] = CartItem(
        producto: producto,
        cantidad: detalle.cantidad.round(),
        notas: detalle.notas,
      );
    }

    _items.addAll(consolidados.values);
    notifyListeners();
  }
}
