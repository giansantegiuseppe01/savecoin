import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../models/orden.dart';
import '../models/detalle_orden.dart';
import '../models/cliente.dart';
import '../models/producto.dart';
import '../models/configuracion.dart';
import '../providers/order_provider.dart';
import '../providers/cart_provider.dart';
import '../providers/config_provider.dart';
import '../providers/product_provider.dart';
import '../widgets/ticket_preview_modal.dart';

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
      context.read<OrderProvider>().loadOrdenesAbiertas();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Órdenes Abiertas'),
        backgroundColor: Colors.blue[700],
      ),
      body: Consumer<OrderProvider>(
        builder: (context, orderProvider, _) {
          if (orderProvider.isLoading) {
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

          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: orderProvider.ordenesAbiertas.length,
            itemBuilder: (context, index) {
              final orden = orderProvider.ordenesAbiertas[index];
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
              // PASO 5A: Reimprimir ticket completo
              final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
              final detalles = detallesBox.values
                  .where((d) => orden.detalleIds.contains(d.id))
                  .toList();

              await TicketPreviewModal.show(
                context: context,
                orden: orden,
                detalles: detalles,
                tipoTicket: 'comanda',
                tasaCambio: config.tasaCambio,
              );

              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Ticket reimprimido')),
                );
              }
            },
            icon: const Icon(Icons.print),
            label: const Text('Reimprimir'),
          ),
          ElevatedButton.icon(
            onPressed: () {
              // CRITICAL FIX: Cerrar el diálogo primero, luego cargar y navegar
              Navigator.of(context).pop(); // Cerrar el diálogo de detalles

              // Seleccionar la orden y cargar detalles
              orderProvider.selectOrden(orden);
              _cargarDetallesEnCarrito(orden);

              // Navegar de vuelta al POS screen
              Navigator.of(context).pop(); // Cerrar la pantalla de órdenes abiertas
            },
            icon: const Icon(Icons.edit),
            label: const Text('Editar Comanda'),
          ),
          ElevatedButton.icon(
            onPressed: () async {
              Navigator.pop(context);
              orderProvider.selectOrden(orden);
              try {
                await orderProvider.updateKitchenCommand();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Comanda actualizada e impresa')),
                  );
                }
              } catch (e) {
                if (e.toString().contains('WEB_SIMULATION:kitchen_command')) {
                  final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
                  final detalles = detallesBox.values
                      .where((d) => orden.detalleIds.contains(d.id) && d.isNew)
                      .toList();
                  
                  if (detalles.isEmpty) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('No hay items nuevos para imprimir')),
                      );
                    }
                    return;
                  }
                  
                  await TicketPreviewModal.show(
                    context: context,
                    orden: orden,
                    detalles: detalles,
                    tipoTicket: 'comanda',
                    tasaCambio: config.tasaCambio,
                  );
                  
                  for (var detalle in detalles) {
                    final updated = detalle.copyWith(isNew: false);
                    await detallesBox.put(detalle.id, updated);
                  }
                  
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Comanda actualizada')),
                    );
                  }
                }
              }
            },
            icon: const Icon(Icons.print),
            label: const Text('Actualizar Comanda'),
          ),
          ElevatedButton.icon(
            onPressed: () async {
              Navigator.pop(context);
              orderProvider.selectOrden(orden);
              try {
                await orderProvider.printPreCuenta(config.tasaCambio);
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Pre-cuenta impresa')),
                  );
                }
              } catch (e) {
                if (e.toString().contains('WEB_SIMULATION:pre_cuenta')) {
                  final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
                  final detalles = detallesBox.values
                      .where((d) => orden.detalleIds.contains(d.id))
                      .toList();
                  
                  await TicketPreviewModal.show(
                    context: context,
                    orden: orden,
                    detalles: detalles,
                    tipoTicket: 'pre_cuenta',
                    tasaCambio: config.tasaCambio,
                  );
                }
              }
            },
            icon: const Icon(Icons.receipt),
            label: const Text('Imprimir Pre-cuenta'),
          ),
        ],
      ),
    );
  }

  void _cargarDetallesEnCarrito(Orden orden) {
    final cartProvider = context.read<CartProvider>();
    final productProvider = context.read<ProductProvider>();
    
    // Asegurarse de que los productos estén cargados
    if (productProvider.productos.isEmpty) {
      productProvider.loadProductos();
    }
    
    // Cargar detalles de la orden en el carrito
    final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
    final detalles = detallesBox.values
        .where((d) => orden.detalleIds.contains(d.id))
        .toList();
    
    // Crear mapa de productos para cargar en carrito
    final productosMap = <String, Producto>{};
    for (var producto in productProvider.productos) {
      productosMap[producto.id] = producto;
    }
    
    cartProvider.loadFromDetallesOrden(detalles, productosMap);
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
                    backgroundColor: Colors.orange[100],
                    labelStyle: TextStyle(color: Colors.orange[900]),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text('Cliente: ${orden.clienteNombre}'),
              Text('Fecha: ${_formatDate(orden.fechaHora)}'),
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
