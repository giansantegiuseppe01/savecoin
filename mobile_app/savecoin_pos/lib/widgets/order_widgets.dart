import 'package:flutter/material.dart';
import '../models/detalle_orden.dart';
import '../models/orden.dart';
import '../providers/order_provider.dart';
import '../utils/formatters.dart';
import 'ticket_preview_modal.dart';

/// Tarjeta de una orden en las listas de órdenes abiertas y cerradas.
class OrdenCard extends StatelessWidget {
  final Orden orden;
  final VoidCallback onTap;

  const OrdenCard({super.key, required this.orden, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final abierta = orden.estado == 'abierta';
    final color = abierta ? Colors.orange : Colors.green;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    orden.numeroRecibo,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  Row(
                    children: [
                      if (orden.isLocal)
                        const Padding(
                          padding: EdgeInsets.only(right: 8),
                          child: Tooltip(
                            message: 'Pendiente de enviar al servidor',
                            child: Icon(Icons.cloud_off, size: 20, color: Colors.grey),
                          ),
                        ),
                      Chip(
                        label: Text(orden.estado.toUpperCase()),
                        backgroundColor: color[100],
                        labelStyle: TextStyle(color: color[900]),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text('Cliente: ${orden.clienteNombre}'),
              Text('Fecha: ${formatFechaHora(orden.fechaHora)}'),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('${orden.detalleIds.length} items'),
                  Text(
                    '\$${orden.total.toStringAsFixed(2)}',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                      color: Colors.green,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Contenido del diálogo de detalle de una orden. La lista de productos usa
/// `Flexible` (no `Expanded`): dentro de un `AlertDialog` el alto no está
/// acotado y `Expanded` rompía el diseño.
class OrdenDetalleContenido extends StatelessWidget {
  final Orden orden;
  final List<DetalleOrden> detalles;

  const OrdenDetalleContenido({
    super.key,
    required this.orden,
    required this.detalles,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 420,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Cliente: ${orden.clienteNombre}'),
          Text('Fecha: ${formatFechaHora(orden.fechaHora)}'),
          Text('Estado: ${orden.estado.toUpperCase()}'),
          if (orden.metodoPago != null && orden.estado == 'cerrada')
            Text('Método de pago: ${orden.metodoPago}'),
          const Divider(),
          const Text('Items:', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Flexible(
            child: detalles.isEmpty
                ? const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: Text('Sin productos'),
                  )
                : ListView.builder(
                    shrinkWrap: true,
                    itemCount: detalles.length,
                    itemBuilder: (context, index) {
                      final d = detalles[index];
                      return ListTile(
                        dense: true,
                        title: Text(d.productoNombre),
                        subtitle: Text(
                            '${formatCantidad(d.cantidad)} x \$${d.precioUnitario.toStringAsFixed(2)}'),
                        trailing: Text('\$${d.subtotal.toStringAsFixed(2)}'),
                      );
                    },
                  ),
          ),
          const Divider(),
          Text('Subtotal: \$${orden.subtotal.toStringAsFixed(2)}'),
          if (orden.aplicaIva) Text('IVA: \$${orden.impuesto.toStringAsFixed(2)}'),
          Text(
            'Total: \$${orden.total.toStringAsFixed(2)}',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
          ),
        ],
      ),
    );
  }
}

/// Muestra el resultado de una acción del POS: la vista previa del ticket (solo
/// en Web), los avisos de stock y un mensaje. Un mismo sitio para las tres
/// pantallas, que antes repetían este código.
Future<void> mostrarResultado(
  BuildContext context,
  AccionResultado resultado, {
  required double tasaCambio,
}) async {
  final messenger = ScaffoldMessenger.of(context);

  final preview = resultado.preview;
  if (preview != null) {
    await TicketPreviewModal.show(
      context: context,
      orden: preview.orden,
      detalles: preview.detalles,
      tipoTicket: preview.tipo,
      tasaCambio: tasaCambio,
      metodoPago: preview.metodoPago,
    );
  }

  if (resultado.mensaje != null) {
    messenger.showSnackBar(SnackBar(
      content: Text(resultado.mensaje!),
      backgroundColor: resultado.ok ? null : Colors.red,
      duration: const Duration(seconds: 5),
    ));
  }

  if (resultado.advertencias.isNotEmpty && context.mounted) {
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Advertencias de stock'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final a in resultado.advertencias)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text('⚠️ $a'),
                ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Entendido'),
          ),
        ],
      ),
    );
  }
}
