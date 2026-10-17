import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/cliente.dart';
import '../providers/cart_provider.dart';

class CartPanel extends StatelessWidget {
  final List<dynamic> cartItems;
  final double subtotal;
  final double impuesto;
  final double total;
  final int itemCount;
  final Cliente? selectedCliente;
  final VoidCallback onClienteSelected;
  final VoidCallback onClearCart;
  final Function(String) onRemoveItem;
  final Function(String, int) onUpdateCantidad;
  final VoidCallback onSendToKitchen;
  final VoidCallback onPrintPreCuenta;
  final Function(String, bool) onCobrar; // Cambiado para aceptar aplicaIva
  final bool isNewCommand; // Nueva propiedad para controlar visibilidad del botón Cobrar

  const CartPanel({
    super.key,
    required this.cartItems,
    required this.subtotal,
    required this.impuesto,
    required this.total,
    required this.itemCount,
    required this.selectedCliente,
    required this.onClienteSelected,
    required this.onClearCart,
    required this.onRemoveItem,
    required this.onUpdateCantidad,
    required this.onSendToKitchen,
    required this.onPrintPreCuenta,
    required this.onCobrar,
    this.isNewCommand = true, // Por defecto true (nueva comanda)
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 350,
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(left: BorderSide(color: Colors.grey[300]!)),
      ),
      child: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.blue[700],
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Pedido Actual',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
                if (itemCount > 0)
                  Chip(
                    label: Text('$itemCount items'),
                    backgroundColor: Colors.white,
                    labelStyle: TextStyle(color: Colors.blue[700]),
                  ),
              ],
            ),
          ),
          
          // Selección de cliente
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.grey[100],
              border: Border(bottom: BorderSide(color: Colors.grey[300]!)),
            ),
            child: InkWell(
              onTap: onClienteSelected,
              child: Row(
                children: [
                  const Icon(Icons.person, color: Colors.blue),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      selectedCliente?.nombreCompleto ?? 'Seleccionar Cliente',
                      style: const TextStyle(
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                  const Icon(Icons.chevron_right),
                ],
              ),
            ),
          ),
          
          // Lista de items
          Expanded(
            child: cartItems.isEmpty
                ? const Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.shopping_cart, size: 64, color: Colors.grey),
                        SizedBox(height: 16),
                        Text('Carrito vacío'),
                      ],
                    ),
                  )
                : ListView.builder(
                    padding: const EdgeInsets.all(8),
                    itemCount: cartItems.length,
                    itemBuilder: (context, index) {
                      final item = cartItems[index];
                      return _CartItem(
                        item: item,
                        onRemove: () => onRemoveItem(item.producto.id),
                        onUpdateCantidad: (cantidad) {
                          onUpdateCantidad(item.producto.id, cantidad);
                        },
                      );
                    },
                  ),
          ),
          
          // Totales
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.grey[50],
              border: Border(top: BorderSide(color: Colors.grey[300]!)),
            ),
            child: Consumer<CartProvider>(
              builder: (context, cartProvider, _) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _TotalRow(label: 'Subtotal', value: '\$${subtotal.toStringAsFixed(2)}'),
                    if (cartProvider.aplicaIva)
                      _TotalRow(label: 'Impuesto (16%)', value: '\$${impuesto.toStringAsFixed(2)}'),
                    const SizedBox(height: 8),
                    _TotalRow(
                      label: 'Total',
                      value: '\$${total.toStringAsFixed(2)}',
                      isBold: true,
                      fontSize: 20,
                    ),
                  ],
                );
              },
            ),
          ),
          
          // Botones de acción
          Container(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (itemCount > 0) ...[
                  ElevatedButton.icon(
                    onPressed: onSendToKitchen,
                    icon: const Icon(Icons.restaurant),
                    label: const Text('Enviar a Cocina'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.orange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                  const SizedBox(height: 8),
                  ElevatedButton.icon(
                    onPressed: onPrintPreCuenta,
                    icon: const Icon(Icons.receipt),
                    label: const Text('Imprimir Pre-cuenta'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      // Botón Cobrar solo visible si NO es una comanda nueva
                      if (!isNewCommand)
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: () => _showPaymentDialog(context),
                            icon: const Icon(Icons.payments),
                            label: const Text('Cobrar'),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.green,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 12),
                            ),
                          ),
                        ),
                      if (!isNewCommand) const SizedBox(width: 8),
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: onSendToKitchen,
                          icon: const Icon(Icons.restaurant),
                          label: const Text('Guardar'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.blue,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton(
                        onPressed: onClearCart,
                        icon: const Icon(Icons.delete),
                        color: Colors.red,
                        tooltip: 'Limpiar carrito',
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _showPaymentDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) {
          final cartProvider = context.watch<CartProvider>();
          final aplicaIva = cartProvider.aplicaIva;
          final double tasaIva = 0.16; // 16% IVA

          return AlertDialog(
            title: const Text('Seleccionar Método de Pago'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Checkbox para aplicar IVA
                CheckboxListTile(
                  title: const Text('Aplicar IVA (16%)'),
                  subtitle: Text('Subtotal: \$${subtotal.toStringAsFixed(2)}'),
                  value: aplicaIva,
                  onChanged: (value) {
                    cartProvider.setAplicaIva(value ?? false);
                  },
                ),
                
                // Mostrar cálculo dinámico
                if (aplicaIva) ...[
                  const SizedBox(height: 8),
                  Text(
                    'IVA: \$${(subtotal * tasaIva).toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 14),
                  ),
                  Text(
                    'Total: \$${(subtotal * (1 + tasaIva)).toStringAsFixed(2)}',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ] else ...[
                  const SizedBox(height: 8),
                  Text(
                    'Total: \$${subtotal.toStringAsFixed(2)}',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
                
                const Divider(),
                const Text('Método de Pago:'),
                ListTile(
                  leading: const Icon(Icons.money),
                  title: const Text('Efectivo'),
                  onTap: () {
                    Navigator.pop(context);
                    onCobrar('efectivo', aplicaIva);
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.credit_card),
                  title: const Text('Tarjeta'),
                  onTap: () {
                    Navigator.pop(context);
                    onCobrar('tarjeta', aplicaIva);
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.account_balance),
                  title: const Text('Transferencia'),
                  onTap: () {
                    Navigator.pop(context);
                    onCobrar('transferencia', aplicaIva);
                  },
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _CartItem extends StatelessWidget {
  final dynamic item;
  final VoidCallback onRemove;
  final Function(int) onUpdateCantidad;

  const _CartItem({
    required this.item,
    required this.onRemove,
    required this.onUpdateCantidad,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    item.producto.nombre,
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close, size: 20),
                  onPressed: onRemove,
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('\$${item.producto.precioVenta.toStringAsFixed(2)}'),
                Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.remove_circle_outline),
                      onPressed: () => onUpdateCantidad(item.cantidad - 1),
                      iconSize: 24,
                    ),
                    Text(
                      '${item.cantidad}',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.add_circle_outline),
                      onPressed: () => onUpdateCantidad(item.cantidad + 1),
                      iconSize: 24,
                    ),
                  ],
                ),
                Text(
                  '\$${item.subtotal.toStringAsFixed(2)}',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _TotalRow extends StatelessWidget {
  final String label;
  final String value;
  final bool isBold;
  final double fontSize;

  const _TotalRow({
    required this.label,
    required this.value,
    this.isBold = false,
    this.fontSize = 14,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
              fontSize: fontSize,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
              fontSize: fontSize,
            ),
          ),
        ],
      ),
    );
  }
}
