import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import '../models/orden.dart';
import '../models/detalle_orden.dart';
import '../utils/constants.dart';

class TicketPreviewModal extends StatelessWidget {
  final Orden orden;
  final List<DetalleOrden> detalles;
  final String tipoTicket; // 'comanda', 'pre_cuenta', 'final', 'reprint'
  final double tasaCambio;
  final String? metodoPago;

  const TicketPreviewModal({
    super.key,
    required this.orden,
    required this.detalles,
    required this.tipoTicket,
    required this.tasaCambio,
    this.metodoPago,
  });

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.white,
      child: Container(
        width: 400,
        constraints: const BoxConstraints(maxHeight: 700),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Header
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: _getHeaderColor(),
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(12),
                  topRight: Radius.circular(12),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    _getTicketTitle(),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
            ),
            
            // Ticket content
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: _buildTicketContent(),
              ),
            ),
            
            // Footer actions
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: const BorderRadius.only(
                  bottomLeft: Radius.circular(12),
                  bottomRight: Radius.circular(12),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  if (kIsWeb)
                    ElevatedButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                      },
                      icon: const Icon(Icons.close),
                      label: const Text('Cerrar'),
                    ),
                  ElevatedButton.icon(
                    onPressed: () {
                      Navigator.pop(context, true);
                    },
                    icon: const Icon(Icons.print),
                    label: const Text('Imprimir'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue[700],
                      foregroundColor: Colors.white,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTicketContent() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header del ticket
          Center(
            child: Column(
              children: [
                if (tipoTicket == 'reprint')
                  const Text(
                    '** COPIA / REIMPRESIÓN **',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: Colors.red,
                    ),
                  ),
                // Detectar si hay modificaciones
                if (tipoTicket == 'comanda' && detalles.any((d) => d.fueModificado))
                  const Text(
                    '** COMANDA MODIFICADA **',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: Colors.orange,
                    ),
                  ),
                Text(
                  tipoTicket == 'comanda' 
                      ? (detalles.any((d) => d.fueModificado) ? 'COMANDA MODIFICADA' : 'COMANDA DE COCINA')
                      : 'SAVECOIN POS',
                  style: TextStyle(
                    fontSize: tipoTicket == 'comanda' ? 16 : 20,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 8),
                if (tipoTicket != 'comanda')
                  const Text('Restaurante & Bar', style: TextStyle(fontSize: 12)),
                const Divider(thickness: 2),
              ],
            ),
          ),
          
          const SizedBox(height: 8),
          
          // Información de la orden
          _buildInfoRow('Orden:', orden.numeroRecibo),
          _buildInfoRow('Cliente:', orden.clienteNombre),
          _buildInfoRow('Fecha:', _formatDate(orden.fechaHora)),
          
          // Agregar notas del cliente si existen
          if (orden.notas != null && orden.notas!.isNotEmpty) ...[
            const Divider(thickness: 1),
            const SizedBox(height: 8),
            const Text('NOTAS DEL CLIENTE:', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.yellow[50],
                border: Border.all(color: Colors.yellow[700]!),
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(
                orden.notas!,
                style: const TextStyle(fontSize: 12),
              ),
            ),
            const SizedBox(height: 8),
          ],
          
          const Divider(thickness: 1),
          const SizedBox(height: 8),
          
          // Items
          const Text('ITEMS:', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          
          ...detalles.map((detalle) => _buildDetalleItem(detalle)),
          
          const Divider(thickness: 1),
          const SizedBox(height: 8),
          
          // Totales
          if (tipoTicket != 'comanda') ...[
            _buildTotalRow('Subtotal:', '\$${orden.subtotal.toStringAsFixed(2)}'),
            // Solo mostrar impuesto si aplica IVA
            if (orden.aplicaIva) ...[
              _buildTotalRow('Impuesto (${(kIvaRate * 100).round()}%):', '\$${orden.impuesto.toStringAsFixed(2)}'),
            ],
            const SizedBox(height: 4),
            _buildTotalRow(
              'Total USD:',
              '\$${orden.total.toStringAsFixed(2)}',
              isBold: true,
              fontSize: 18,
            ),
            _buildTotalRow(
              'Total Bs:',
              '${(orden.total * tasaCambio).toStringAsFixed(2)}',
              isBold: true,
              fontSize: 18,
            ),
            const Divider(thickness: 1),
            _buildInfoRow('Tasa:', '\$1 = Bs${tasaCambio.toStringAsFixed(2)}'),
          ],
          
          if (tipoTicket == 'final' && metodoPago != null) ...[
            const Divider(thickness: 1),
            _buildInfoRow('Método de Pago:', metodoPago!),
          ],
          
          const SizedBox(height: 16),
          
          // Footer
          Center(
            child: Text(
              tipoTicket == 'comanda' ? '--- FIN COMANDA ---' : '¡Gracias por su compra!',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 12)),
          Text(value, style: const TextStyle(fontSize: 12)),
        ],
      ),
    );
  }

  Widget _buildDetalleItem(DetalleOrden detalle) {
    final prefijo = detalle.fueModificado ? '* ' : '';
    
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  '$prefijo${detalle.productoNombre}',
                  style: TextStyle(
                    fontWeight: FontWeight.bold, 
                    fontSize: 12,
                    color: detalle.fueModificado ? Colors.orange : Colors.black,
                  ),
                ),
              ),
              if (tipoTicket != 'comanda')
                Text(
                  '\$${detalle.subtotal.toStringAsFixed(2)}',
                  style: const TextStyle(fontSize: 12),
                ),
            ],
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '${detalle.cantidad} x \$${detalle.precioUnitario.toStringAsFixed(2)}',
                style: const TextStyle(fontSize: 11, color: Colors.grey),
              ),
              if (detalle.notas != null && detalle.notas!.isNotEmpty)
                Text(
                  'Nota: ${detalle.notas}',
                  style: const TextStyle(fontSize: 10, fontStyle: FontStyle.italic),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTotalRow(String label, String value, {bool isBold = false, double fontSize = 14}) {
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

  String _getTicketTitle() {
    switch (tipoTicket) {
      case 'comanda':
        return 'Comanda de Cocina';
      case 'pre_cuenta':
        return 'Pre-Cuenta';
      case 'final':
        return 'Ticket Final';
      case 'reprint':
        return 'Reimpresión';
      default:
        return 'Ticket';
    }
  }

  Color _getHeaderColor() {
    switch (tipoTicket) {
      case 'comanda':
        return Colors.orange;
      case 'pre_cuenta':
        return Colors.blue;
      case 'final':
        return Colors.green;
      case 'reprint':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
  }

  static Future<bool?> show({
    required BuildContext context,
    required Orden orden,
    required List<DetalleOrden> detalles,
    required String tipoTicket,
    required double tasaCambio,
    String? metodoPago,
  }) {
    return showDialog<bool>(
      context: context,
      builder: (context) => TicketPreviewModal(
        orden: orden,
        detalles: detalles,
        tipoTicket: tipoTicket,
        tasaCambio: tasaCambio,
        metodoPago: metodoPago,
      ),
    );
  }
}
