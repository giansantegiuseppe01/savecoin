import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../models/orden.dart';
import '../models/detalle_orden.dart';
import '../models/configuracion.dart';
import '../providers/order_provider.dart';
import '../providers/config_provider.dart';
import '../widgets/ticket_preview_modal.dart';
import '../services/bluetooth_service.dart';

class ClosedOrdersScreen extends StatefulWidget {
  const ClosedOrdersScreen({super.key});

  @override
  State<ClosedOrdersScreen> createState() => _ClosedOrdersScreenState();
}

class _ClosedOrdersScreenState extends State<ClosedOrdersScreen> {
  final BluetoothService _bluetoothService = BluetoothService();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OrderProvider>().loadOrdenesCerradas();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Órdenes Cerradas'),
        backgroundColor: Colors.blue[700],
      ),
      body: Consumer<OrderProvider>(
        builder: (context, orderProvider, _) {
          if (orderProvider.isLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          if (orderProvider.ordenesCerradas.isEmpty) {
            return const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.archive, size: 64, color: Colors.grey),
                  SizedBox(height: 16),
                  Text('No hay órdenes cerradas'),
                ],
              ),
            );
          }

          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: orderProvider.ordenesCerradas.length,
            itemBuilder: (context, index) {
              final orden = orderProvider.ordenesCerradas[index];
              return _OrdenCard(
                orden: orden,
                onTap: () => _showOrdenDetails(orden, orderProvider),
              );
            },
          );
        },
      ),
    );
  }

  Future<void> _showOrdenDetails(Orden orden, OrderProvider orderProvider) async {
    final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
    final detalles = detallesBox.values
        .where((d) => orden.detalleIds.contains(d.id))
        .toList();

    final config = context.read<ConfigProvider>().config;
    if (config == null) return;

    await showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Orden ${orden.numeroRecibo}'),
        content: SizedBox(
          width: double.maxFinite,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Cliente: ${orden.clienteNombre}'),
              Text('Fecha: ${_formatDate(orden.fechaHora)}'),
              Text('Estado: ${orden.estado.toUpperCase()}'),
              if (orden.metodoPago != null) Text('Método de Pago: ${orden.metodoPago}'),
              const Divider(),
              const Text('Items:', style: TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              Expanded(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: detalles.length,
                  itemBuilder: (context, index) {
                    final detalle = detalles[index];
                    return ListTile(
                      title: Text(detalle.productoNombre),
                      subtitle: Text('${detalle.cantidad}x \$${detalle.precioUnitario.toStringAsFixed(2)}'),
                      trailing: Text('\$${detalle.subtotal.toStringAsFixed(2)}'),
                    );
                  },
                ),
              ),
              const Divider(),
              Text('Subtotal: \$${orden.subtotal.toStringAsFixed(2)}'),
              if (orden.aplicaIva) Text('IVA: \$${orden.impuesto.toStringAsFixed(2)}'),
              Text('Total: \$${orden.total.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cerrar'),
          ),
          ElevatedButton.icon(
            onPressed: () async {
              Navigator.pop(context);
              await _reimprimirRecibo(orden, detalles, config);
            },
            icon: const Icon(Icons.print),
            label: const Text('Reimprimir Recibo'),
          ),
        ],
      ),
    );
  }

  Future<void> _reimprimirRecibo(Orden orden, List<DetalleOrden> detalles, Configuracion config) async {
    try {
      await _bluetoothService.reprintTicket(
        orden,
        detalles,
        config.tasaCambio,
        orden.metodoPago ?? 'efectivo',
      );
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Recibo reimpreso exitosamente')),
        );
      }
    } catch (e) {
      if (e.toString().contains('WEB_SIMULATION:reprint')) {
        await TicketPreviewModal.show(
          context: context,
          orden: orden,
          detalles: detalles,
          tipoTicket: 'reprint',
          tasaCambio: config.tasaCambio,
          metodoPago: orden.metodoPago ?? 'efectivo',
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Recibo reimpreso (simulación)')),
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Error al reimprimir: $e')),
          );
        }
      }
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year} ${date.hour}:${date.minute}';
  }
}

class _OrdenCard extends StatelessWidget {
  final Orden orden;
  final VoidCallback onTap;

  const _OrdenCard({
    required this.orden,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
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
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                  Chip(
                    label: Text(orden.estado.toUpperCase()),
                    backgroundColor: Colors.green[100],
                    labelStyle: TextStyle(color: Colors.green[900]),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text('Cliente: ${orden.clienteNombre}'),
              Text('Fecha: ${_formatDate(orden.fechaHora)}'),
              if (orden.metodoPago != null) Text('Pago: ${orden.metodoPago}'),
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

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year} ${date.hour}:${date.minute}';
  }
}