import 'package:flutter/material.dart';
import '../models/cliente.dart';
import '../providers/cart_provider.dart';
import '../utils/constants.dart';

class CartPanel extends StatelessWidget {
  final List<CartItem> cartItems;
  final double subtotal;
  final double impuesto;
  final double total;
  final int itemCount;
  final bool aplicaIva;
  final Cliente? selectedCliente;

  /// Número de la orden que se está editando (null = comanda nueva).
  final String? ordenEnEdicion;

  /// true mientras se guarda/cobra: desactiva los botones para evitar
  /// envíos o cobros dobles.
  final bool busy;
  final VoidCallback onClienteSelected;
  final VoidCallback onClearCart;
  final void Function(String) onRemoveItem;
  final void Function(String, int) onUpdateCantidad;
  final VoidCallback onSendToKitchen;
  final VoidCallback onPrintPreCuenta;
  final void Function(bool) onAplicaIvaChanged;
  final Future<void> Function(String metodoPago, bool aplicaIva,
      {double? montoEntregado, String? referencia}) onCobrar;

  const CartPanel({
    super.key,
    required this.cartItems,
    required this.subtotal,
    required this.impuesto,
    required this.total,
    required this.itemCount,
    required this.aplicaIva,
    required this.selectedCliente,
    required this.onClienteSelected,
    required this.onClearCart,
    required this.onRemoveItem,
    required this.onUpdateCantidad,
    required this.onSendToKitchen,
    required this.onPrintPreCuenta,
    required this.onAplicaIvaChanged,
    required this.onCobrar,
    this.ordenEnEdicion,
    this.busy = false,
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
                Flexible(
                  child: Text(
                    ordenEnEdicion == null ? 'Pedido Actual' : 'Editando $ordenEnEdicion',
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
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
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _TotalRow(label: 'Subtotal', value: '\$${subtotal.toStringAsFixed(2)}'),
                if (aplicaIva)
                  _TotalRow(
                    label: 'Impuesto (${(kIvaRate * 100).round()}%)',
                    value: '\$${impuesto.toStringAsFixed(2)}',
                  ),
                const SizedBox(height: 8),
                _TotalRow(
                  label: 'Total',
                  value: '\$${total.toStringAsFixed(2)}',
                  isBold: true,
                  fontSize: 20,
                ),
              ],
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
                    onPressed: busy ? null : onSendToKitchen,
                    icon: const Icon(Icons.restaurant),
                    label: Text(ordenEnEdicion == null
                        ? 'Enviar a Cocina'
                        : 'Guardar y Enviar a Cocina'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.orange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                  const SizedBox(height: 8),
                  ElevatedButton.icon(
                    onPressed: busy ? null : onPrintPreCuenta,
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
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: busy ? null : () => _cobrar(context),
                          icon: const Icon(Icons.payments),
                          label: const Text('Cobrar'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.green,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton(
                        onPressed: busy ? null : onClearCart,
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

  /// Abre el diálogo de pago y, si el usuario confirma, llama a [onCobrar].
  ///
  /// Antes el diálogo se cerraba antes de abrir el de efectivo/transferencia y
  /// luego se comprobaba `context.mounted` del diálogo ya cerrado (siempre
  /// false), así que el cobro en efectivo o por transferencia nunca se
  /// ejecutaba. Ahora el diálogo devuelve la selección completa.
  Future<void> _cobrar(BuildContext context) async {
    final pago = await showDialog<_Pago>(
      context: context,
      builder: (_) => _PagoDialog(subtotal: subtotal, aplicaIvaInicial: aplicaIva),
    );
    if (pago == null) return;
    onAplicaIvaChanged(pago.aplicaIva);
    await onCobrar(
      pago.metodo,
      pago.aplicaIva,
      montoEntregado: pago.montoEntregado,
      referencia: pago.referencia,
    );
  }
}

class _Pago {
  final String metodo;
  final bool aplicaIva;
  final double? montoEntregado;
  final String? referencia;

  const _Pago(this.metodo, this.aplicaIva, {this.montoEntregado, this.referencia});
}

class _PagoDialog extends StatefulWidget {
  final double subtotal;
  final bool aplicaIvaInicial;

  const _PagoDialog({required this.subtotal, required this.aplicaIvaInicial});

  @override
  State<_PagoDialog> createState() => _PagoDialogState();
}

class _PagoDialogState extends State<_PagoDialog> {
  late bool _aplicaIva = widget.aplicaIvaInicial;

  double get _iva => _aplicaIva ? widget.subtotal * kIvaRate : 0.0;
  double get _total => widget.subtotal + _iva;

  Future<void> _efectivo() async {
    final monto = await showDialog<double>(
      context: context,
      builder: (_) => _EfectivoModal(total: _total),
    );
    if (monto != null && mounted) {
      Navigator.pop(context, _Pago('efectivo', _aplicaIva, montoEntregado: monto));
    }
  }

  Future<void> _transferencia() async {
    final referencia = await showDialog<String>(
      context: context,
      builder: (_) => const _TransferenciaModal(),
    );
    if (referencia != null && referencia.isNotEmpty && mounted) {
      Navigator.pop(context, _Pago('transferencia', _aplicaIva, referencia: referencia));
    }
  }

  @override
  Widget build(BuildContext context) {
    final porcentaje = (kIvaRate * 100).round();
    return AlertDialog(
      title: const Text('Seleccionar Método de Pago'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CheckboxListTile(
              title: Text('Aplicar IVA ($porcentaje%)'),
              subtitle: Text('Subtotal: \$${widget.subtotal.toStringAsFixed(2)}'),
              value: _aplicaIva,
              onChanged: (v) => setState(() => _aplicaIva = v ?? false),
            ),
            if (_aplicaIva) ...[
              const SizedBox(height: 8),
              Text('IVA: \$${_iva.toStringAsFixed(2)}', style: const TextStyle(fontSize: 14)),
            ],
            const SizedBox(height: 4),
            Text(
              'Total: \$${_total.toStringAsFixed(2)}',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const Divider(),
            const Text('Método de Pago:'),
            ListTile(
              leading: const Icon(Icons.money),
              title: const Text('Efectivo'),
              onTap: _efectivo,
            ),
            ListTile(
              leading: const Icon(Icons.credit_card),
              title: const Text('Tarjeta'),
              onTap: () => Navigator.pop(context, _Pago('tarjeta', _aplicaIva)),
            ),
            ListTile(
              leading: const Icon(Icons.account_balance),
              title: const Text('Transferencia'),
              onTap: _transferencia,
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancelar'),
        ),
      ],
    );
  }
}

// Modal de efectivo con cálculo de vuelto
class _EfectivoModal extends StatefulWidget {
  final double total;

  const _EfectivoModal({required this.total});

  @override
  State<_EfectivoModal> createState() => _EfectivoModalState();
}

class _EfectivoModalState extends State<_EfectivoModal> {
  final _montoController = TextEditingController();

  @override
  void dispose() {
    _montoController.dispose();
    super.dispose();
  }

  double? get _monto => double.tryParse(_montoController.text.trim().replaceAll(',', '.'));

  double get vuelto => (_monto ?? 0) - widget.total;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Pago en Efectivo'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('Total a pagar: \$${widget.total.toStringAsFixed(2)}'),
          const SizedBox(height: 16),
          TextField(
            controller: _montoController,
            decoration: const InputDecoration(
              labelText: 'Monto entregado',
              border: OutlineInputBorder(),
              prefixText: '\$',
            ),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 16),
          Text(
            'Vuelto: \$${vuelto.toStringAsFixed(2)}',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: vuelto >= 0 ? Colors.green : Colors.red,
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancelar'),
        ),
        ElevatedButton(
          onPressed: _monto != null && vuelto >= -0.005
              ? () => Navigator.pop(context, _monto)
              : null,
          child: const Text('Aceptar'),
        ),
      ],
    );
  }
}

// Modal de transferencia con referencia bancaria
class _TransferenciaModal extends StatefulWidget {
  const _TransferenciaModal();

  @override
  State<_TransferenciaModal> createState() => _TransferenciaModalState();
}

class _TransferenciaModalState extends State<_TransferenciaModal> {
  final _referenciaController = TextEditingController();

  @override
  void dispose() {
    _referenciaController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Transferencia Bancaria'),
      content: TextField(
        controller: _referenciaController,
        decoration: const InputDecoration(
          labelText: 'Referencia Bancaria',
          border: OutlineInputBorder(),
          hintText: 'ID de la transacción',
        ),
        // Sin esto el botón "Aceptar" nunca se habilitaba
        onChanged: (_) => setState(() {}),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancelar'),
        ),
        ElevatedButton(
          onPressed: _referenciaController.text.trim().isNotEmpty
              ? () => Navigator.pop(context, _referenciaController.text.trim())
              : null,
          child: const Text('Aceptar'),
        ),
      ],
    );
  }
}

class _CartItem extends StatelessWidget {
  final CartItem item;
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
