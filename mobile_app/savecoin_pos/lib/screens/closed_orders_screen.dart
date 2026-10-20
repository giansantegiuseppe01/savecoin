import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/orden.dart';
import '../providers/config_provider.dart';
import '../providers/order_provider.dart';
import '../widgets/order_widgets.dart';

class ClosedOrdersScreen extends StatefulWidget {
  const ClosedOrdersScreen({super.key});

  @override
  State<ClosedOrdersScreen> createState() => _ClosedOrdersScreenState();
}

class _ClosedOrdersScreenState extends State<ClosedOrdersScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OrderProvider>().loadOrdenes();
    });
  }

  double get _tasaCambio => context.read<ConfigProvider>().config?.tasaCambio ?? 1;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Órdenes Cerradas'),
        backgroundColor: Colors.blue[700],
      ),
      body: Consumer<OrderProvider>(
        builder: (context, orderProvider, _) {
          if (orderProvider.isLoading && orderProvider.ordenesCerradas.isEmpty) {
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

          return RefreshIndicator(
            onRefresh: orderProvider.loadOrdenes,
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: orderProvider.ordenesCerradas.length,
              itemBuilder: (context, index) {
                final orden = orderProvider.ordenesCerradas[index];
                return OrdenCard(
                  orden: orden,
                  onTap: () => _showOrdenDetails(orden, orderProvider),
                );
              },
            ),
          );
        },
      ),
    );
  }

  Future<void> _showOrdenDetails(Orden orden, OrderProvider orderProvider) async {
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Orden ${orden.numeroRecibo}'),
        content: OrdenDetalleContenido(
          orden: orden,
          detalles: orderProvider.detallesDe(orden),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cerrar'),
          ),
          ElevatedButton.icon(
            onPressed: () async {
              Navigator.pop(dialogContext);
              final r = await orderProvider.reimprimirTicket(
                  orden, orden.tasaCambio > 0 ? orden.tasaCambio : _tasaCambio);
              if (mounted) await mostrarResultado(context, r, tasaCambio: _tasaCambio);
            },
            icon: const Icon(Icons.print),
            label: const Text('Reimprimir Recibo'),
          ),
        ],
      ),
    );
  }
}
