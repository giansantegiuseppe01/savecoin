import 'package:flutter/material.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:provider/provider.dart';
import '../models/orden.dart';
import '../models/producto.dart';
import '../providers/cart_provider.dart';
import '../providers/config_provider.dart';
import '../providers/order_provider.dart';
import '../widgets/order_widgets.dart';

class OpenOrdersScreen extends StatefulWidget {
  const OpenOrdersScreen({super.key});

  @override
  State<OpenOrdersScreen> createState() => _OpenOrdersScreenState();
}

class _OpenOrdersScreenState extends State<OpenOrdersScreen> {
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
        title: const Text('Órdenes Abiertas'),
        backgroundColor: Colors.blue[700],
      ),
      body: Consumer<OrderProvider>(
        builder: (context, orderProvider, _) {
          if (orderProvider.isLoading && orderProvider.ordenesAbiertas.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }

          if (orderProvider.ordenesAbiertas.isEmpty) {
            return const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.receipt_long, size: 64, color: Colors.grey),
                  SizedBox(height: 16),
                  Text('No hay órdenes abiertas'),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: orderProvider.loadOrdenes,
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: orderProvider.ordenesAbiertas.length,
              itemBuilder: (context, index) {
                final orden = orderProvider.ordenesAbiertas[index];
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
    final detalles = orderProvider.detallesDe(orden);

    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Orden ${orden.numeroRecibo}'),
        content: OrdenDetalleContenido(orden: orden, detalles: detalles),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cerrar'),
          ),
          ElevatedButton.icon(
            onPressed: () async {
              Navigator.pop(dialogContext);
              final r = await orderProvider.reimprimirComanda(orden);
              if (mounted) await mostrarResultado(context, r, tasaCambio: _tasaCambio);
            },
            icon: const Icon(Icons.print),
            label: const Text('Reimprimir Comanda'),
          ),
          ElevatedButton.icon(
            onPressed: () async {
              Navigator.pop(dialogContext);
              final r =
                  await orderProvider.imprimirPreCuentaDeOrden(orden, _tasaCambio);
              if (mounted) await mostrarResultado(context, r, tasaCambio: _tasaCambio);
            },
            icon: const Icon(Icons.receipt),
            label: const Text('Pre-cuenta'),
          ),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.pop(dialogContext); // cierra el diálogo
              _editarOrden(orden, orderProvider);
              Navigator.pop(context); // vuelve al POS
            },
            icon: const Icon(Icons.edit),
            label: const Text('Editar Comanda'),
          ),
        ],
      ),
    );
  }

  /// Deja la orden como orden en edición y pasa sus productos al carrito.
  void _editarOrden(Orden orden, OrderProvider orderProvider) {
    final productos = Hive.box<Producto>('productos');
    final mapa = {for (final p in productos.values) p.id: p};

    orderProvider.selectOrden(orden);
    context.read<CartProvider>().loadFromDetallesOrden(
          orderProvider.detallesDe(orden),
          mapa,
          aplicaIva: orden.aplicaIva,
        );
  }
}
