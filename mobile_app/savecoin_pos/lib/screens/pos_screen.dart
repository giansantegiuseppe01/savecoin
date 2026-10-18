import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:provider/provider.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../models/producto.dart';
import '../models/cliente.dart';
import '../models/orden.dart';
import '../models/detalle_orden.dart';
import '../models/configuracion.dart';
import '../providers/product_provider.dart';
import '../providers/cart_provider.dart';
import '../providers/order_provider.dart';
import '../providers/config_provider.dart';
import '../providers/client_provider.dart';
import '../providers/shift_provider.dart';
import '../services/api_service.dart';
import '../widgets/product_card.dart';
import '../widgets/cart_panel.dart';
import '../widgets/category_sidebar.dart';
import '../widgets/ticket_preview_modal.dart';
import 'client_selector_screen.dart';
import 'open_orders_screen.dart';
import 'closed_orders_screen.dart';
import 'config_screen.dart';
import 'initial_screen.dart';

class POSScreen extends StatefulWidget {
  const POSScreen({super.key});

  @override
  State<POSScreen> createState() => _POSScreenState();
}

class _POSScreenState extends State<POSScreen> {
  int _stockBajoCount = 0;
  bool _isLoadingAlerts = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProductProvider>().loadProductos();
      context.read<ConfigProvider>().loadConfig();
      context.read<OrderProvider>().loadOrdenesAbiertas();
      context.read<ClientProvider>().loadClientes();
      _loadStockAlerts();
    });
  }

  Future<void> _loadStockAlerts() async {
    setState(() => _isLoadingAlerts = true);
    try {
      final apiService = ApiService();
      final alerts = await apiService.getStockAlerts();
      if (mounted) {
        setState(() {
          _stockBajoCount = alerts['counters']['stock_bajo'] ?? 0;
          _isLoadingAlerts = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoadingAlerts = false);
      }
    }
  }

  Future<void> _actualizarOrdenEnTiempoReal(CartProvider cartProvider, OrderProvider orderProvider) async {
    if (orderProvider.currentOrden == null) return;
    
    try {
      final detallesNuevos = cartProvider.toDetallesOrden(orderProvider.currentOrden!.id);
      await orderProvider.updateOrden(detallesNuevos);
    } catch (e) {
      print('Error al actualizar orden en tiempo real: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('SaveCoin POS'),
        backgroundColor: Colors.blue[700],
        actions: [
          if (_stockBajoCount > 0)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8.0),
              child: Badge(
                label: Text('$_stockBajoCount'),
                child: IconButton(
                  icon: const Icon(Icons.warning_amber, color: Colors.amber),
                  tooltip: 'Stock Bajo',
                  onPressed: () {
                    showDialog(
                      context: context,
                      builder: (context) => AlertDialog(
                        title: const Text('Alertas de Stock'),
                        content: Text('Hay $_stockBajoCount insumos con stock bajo.\n\nVerifica el inventario en el sistema web.'),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(context),
                            child: const Text('Entendido'),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ),
          IconButton(
            icon: const Icon(Icons.sync),
            tooltip: 'Sincronizar',
            onPressed: () async {
              final syncService = context.read<OrderProvider>().syncService;
              try {
                final result = await syncService.manualSync();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(result['message']),
                      backgroundColor: result['success'] ? Colors.green : Colors.red,
                    ),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Error de sincronización: $e')),
                  );
                }
              }
            },
          ),
          IconButton(
            icon: const Icon(Icons.receipt_long),
            tooltip: 'Órdenes Abiertas',
            onPressed: () async {
              print('Botón Órdenes Abiertas presionado');
              await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const OpenOrdersScreen()),
              );
              // No llamar a _cargarOrdenSeleccionada aquí, solo cuando se selecciona "Editar Comanda"
            },
          ),
          IconButton(
            icon: const Icon(Icons.archive),
            tooltip: 'Órdenes Cerradas',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ClosedOrdersScreen()),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.close),
            tooltip: 'Cerrar Turno',
            onPressed: () async {
              final shiftProvider = context.read<ShiftProvider>();
              final confirmed = await showDialog<bool>(
                context: context,
                builder: (context) => AlertDialog(
                  title: const Text('Cerrar Turno'),
                  content: const Text('¿Está seguro de cerrar el turno? Esto consolidará las ventas en el libro contable.'),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(context, false),
                      child: const Text('Cancelar'),
                    ),
                    TextButton(
                      onPressed: () => Navigator.pop(context, true),
                      child: const Text('Cerrar Turno'),
                    ),
                  ],
                ),
              );

              if (confirmed == true && mounted) {
                final success = await shiftProvider.closeShift();
                if (success) {
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Turno cerrado exitosamente'),
                        backgroundColor: Colors.green,
                      ),
                    );
                    Navigator.of(context).pushReplacement(
                      MaterialPageRoute(builder: (_) => const InitialScreen()),
                    );
                  }
                } else if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Error al cerrar turno'),
                      backgroundColor: Colors.red,
                    ),
                  );
                }
              }
            },
          ),
          IconButton(
            icon: const Icon(Icons.settings),
            tooltip: 'Configuración',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ConfigScreen()),
              );
            },
          ),
        ],
      ),
      body: Consumer3<ProductProvider, CartProvider, OrderProvider>(
        builder: (context, productProvider, cartProvider, orderProvider, _) {
          return Row(
            children: [
              // Sidebar de Categorías (Izquierda)
              CategorySidebar(
                categories: productProvider.categorias,
                selectedCategory: productProvider.selectedCategoria,
                onCategorySelected: (categoria) {
                  productProvider.filterByCategoria(categoria);
                },
              ),
              
              // Grid de Productos (Centro)
              Expanded(
                flex: 3,
                child: Container(
                  color: Colors.grey[100],
                  child: Column(
                    children: [
                      // Barra de búsqueda
                      Padding(
                        padding: const EdgeInsets.all(16.0),
                        child: TextField(
                          decoration: InputDecoration(
                            hintText: 'Buscar productos...',
                            prefixIcon: const Icon(Icons.search),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                            filled: true,
                            fillColor: Colors.white,
                          ),
                          onChanged: (query) {
                            productProvider.searchProductos(query);
                          },
                        ),
                      ),
                      
                      // Grid de productos
                      Expanded(
                        child: productProvider.isLoading
                            ? const Center(child: CircularProgressIndicator())
                            : productProvider.productos.isEmpty
                                ? const Center(
                                    child: Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Icon(Icons.inventory_2, size: 64, color: Colors.grey),
                                        SizedBox(height: 16),
                                        Text('No hay productos disponibles'),
                                      ],
                                    ),
                                  )
                                : GridView.builder(
                                    padding: const EdgeInsets.all(16),
                                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                      crossAxisCount: 3,
                                      crossAxisSpacing: 16,
                                      mainAxisSpacing: 16,
                                      childAspectRatio: 0.8,
                                    ),
                                    itemCount: productProvider.productos.length,
                                    itemBuilder: (context, index) {
                                      final producto = productProvider.productos[index];
                                      return ProductCard(
                                        producto: producto,
                                        onTap: () {
                                          cartProvider.addItem(producto);
                                        },
                                      );
                                    },
                                  ),
                      ),
                    ],
                  ),
                ),
              ),
              
              // Panel del Carrito (Derecha)
              CartPanel(
                cartItems: cartProvider.items,
                subtotal: cartProvider.subtotal,
                impuesto: cartProvider.impuesto,
                total: cartProvider.total,
                itemCount: cartProvider.itemCount,
                selectedCliente: orderProvider.selectedCliente,
                isNewCommand: orderProvider.currentOrden == null, // True si es nueva comanda
                onClienteSelected: () async {
                  final cliente = await Navigator.push<Cliente?>(
                    context,
                    MaterialPageRoute(builder: (_) => const ClientSelectorScreen()),
                  );
                  if (cliente != null) {
                    orderProvider.selectCliente(cliente);
                  }
                },
                onClearCart: () {
                  cartProvider.clear();
                },
                onRemoveItem: (productoId) {
                  cartProvider.removeItem(productoId);
                },
                onUpdateCantidad: (productoId, cantidad) {
                  cartProvider.updateCantidad(productoId, cantidad);
                  
                  // Si hay una orden existente, actualizarla en tiempo real
                  if (orderProvider.currentOrden != null) {
                    _actualizarOrdenEnTiempoReal(cartProvider, orderProvider);
                  }
                },
                onSendToKitchen: () async {
                  // PASO 4: Validación de cliente
                  if (orderProvider.selectedCliente == null) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Debe seleccionar un cliente antes de enviar la comanda'),
                          backgroundColor: Colors.red,
                        ),
                      );
                    }
                    return;
                  }

                  // PASO 4: Modal de descripción/exigencias del cliente
                  String? notasCliente;
                  if (mounted) {
                    notasCliente = await showDialog<String>(
                      context: context,
                      builder: (context) => AlertDialog(
                        title: const Text('Notas del Cliente'),
                        content: TextField(
                          decoration: const InputDecoration(
                            hintText: 'Ej: Sin cebolla, Término medio, etc.',
                            border: OutlineInputBorder(),
                          ),
                          maxLines: 3,
                          onChanged: (value) => notasCliente = value,
                        ),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(context, ''),
                            child: const Text('Omitir'),
                          ),
                          ElevatedButton(
                            onPressed: () => Navigator.pop(context, notasCliente),
                            child: const Text('Aceptar'),
                          ),
                        ],
                      ),
                    );
                  }

                  if (orderProvider.currentOrden == null) {
                    final config = context.read<ConfigProvider>().config;
                    if (config == null) return;

                    await orderProvider.createNewOrden(
                      orderProvider.selectedCliente,
                      config.tasaCambio,
                    );

                    // Esperar un momento para que el provider se actualice
                    await Future.delayed(const Duration(milliseconds: 100));
                  }

                  if (orderProvider.currentOrden == null) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Error: No se pudo crear la orden')),
                      );
                    }
                    return;
                  }

                  // Actualizar notas de la orden
                  if (notasCliente != null && notasCliente!.isNotEmpty) {
                    orderProvider.updateOrdenNotas(notasCliente!);
                  }

                  await orderProvider.updateOrden(cartProvider.toDetallesOrden(orderProvider.currentOrden!.id));

                  // PASO 4: Modal de previa antes de enviar
                  final confirmar = await showDialog<bool>(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text('Confirmar Comanda'),
                      content: const Text('¿Desea enviar esta comanda a cocina?'),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(context, false),
                          child: const Text('Modificar'),
                        ),
                        ElevatedButton(
                          onPressed: () => Navigator.pop(context, true),
                          child: const Text('Confirmar'),
                        ),
                      ],
                    ),
                  );

                  if (confirmar != true) return;

                  try {
                    await orderProvider.sendToKitchen();
                    cartProvider.clear();

                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Comanda enviada a cocina')),
                      );
                    }
                  } catch (e) {
                    // Handle Web simulation
                    if (e.toString().contains('WEB_SIMULATION:kitchen_command')) {
                      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
                      final detalles = detallesBox.values
                          .where((d) => orderProvider.currentOrden!.detalleIds.contains(d.id) && d.isNew)
                          .toList();
                      
                      await TicketPreviewModal.show(
                        context: context,
                        orden: orderProvider.currentOrden!,
 detalles: detalles,
                        tipoTicket: 'comanda',
                        tasaCambio: context.read<ConfigProvider>().config?.tasaCambio ?? 36.5,
                      );
                      
                      // Mark as printed
                      for (var detalle in detalles) {
                        final updated = detalle.copyWith(isNew: false);
                        await detallesBox.put(detalle.id, updated);
                      }
                      
                      cartProvider.clear();
                    }
                  }
                },
                onPrintPreCuenta: () async {
                  final config = context.read<ConfigProvider>().config;
                  if (config == null) return;

                  if (orderProvider.currentOrden == null) {
                    await orderProvider.createNewOrden(
                      orderProvider.selectedCliente,
                      config.tasaCambio,
                    );

                    // Esperar un momento para que el provider se actualice
                    await Future.delayed(const Duration(milliseconds: 100));
                  }

                  if (orderProvider.currentOrden == null) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Error: No se pudo crear la orden')),
                      );
                    }
                    return;
                  }

                  await orderProvider.updateOrden(cartProvider.toDetallesOrden(orderProvider.currentOrden!.id));
                  
                  try {
                    await orderProvider.printPreCuenta(config.tasaCambio);
                    
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Pre-cuenta impresa')),
                      );
                    }
                  } catch (e) {
                    // Handle Web simulation
                    if (e.toString().contains('WEB_SIMULATION:pre_cuenta')) {
                      final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
                      final detalles = detallesBox.values
                          .where((d) => orderProvider.currentOrden!.detalleIds.contains(d.id))
                          .toList();
                      
                      await TicketPreviewModal.show(
                        context: context,
                        orden: orderProvider.currentOrden!,
                        detalles: detalles,
                        tipoTicket: 'pre_cuenta',
                        tasaCambio: config.tasaCambio,
                      );
                    }
                  }
                },
                onCobrar: (metodoPago, aplicaIva, {montoEntregado, referencia}) async {
                  final config = context.read<ConfigProvider>().config;
                  if (config == null) return;

                  // Set IVA status in cart provider
                  cartProvider.setAplicaIva(aplicaIva);

                  // CRITICAL FIX: Solo crear nueva orden si no existe una actual
                  if (orderProvider.currentOrden == null) {
                    await orderProvider.createNewOrden(
                      orderProvider.selectedCliente,
                      config.tasaCambio,
                    );

                    // Esperar un momento para que el provider se actualice
                    await Future.delayed(const Duration(milliseconds: 100));
                  }

                  if (orderProvider.currentOrden == null) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Error: No se pudo crear la orden')),
                      );
                    }
                    return;
                  }

                  await orderProvider.updateOrden(cartProvider.toDetallesOrden(orderProvider.currentOrden!.id));

                  // PASO 6: Cerrar orden con información de pago
                  await orderProvider.closeOrden(
                    metodoPago: metodoPago,
                    montoEntregado: montoEntregado,
                    referencia: referencia,
                  );

                  // Para Web, siempre usar el modo de simulación para mostrar el modal
                  if (kIsWeb) {
                    final detallesBox = Hive.box<DetalleOrden>('detalles_orden');
                    final detalles = detallesBox.values
                        .where((d) => orderProvider.currentOrden!.detalleIds.contains(d.id))
                        .toList();

                    final result = await TicketPreviewModal.show(
                      context: context,
                      orden: orderProvider.currentOrden!,
                      detalles: detalles,
                      tipoTicket: 'final',
                      tasaCambio: config.tasaCambio,
                      metodoPago: metodoPago,
                    );

                    if (result == true) {
                      // User confirmed "print", complete the order
                      await orderProvider.closeOrdenConIva(metodoPago, config.tasaCambio, aplicaIva);
                      cartProvider.clear();

                      // Mostrar advertencias de stock si hay
                      if (orderProvider.stockWarnings.isNotEmpty && mounted) {
                        showDialog(
                          context: context,
                          builder: (context) => AlertDialog(
                            title: const Text('Advertencias de Stock'),
                            content: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: orderProvider.stockWarnings.map((warning) => 
                                Padding(
                                  padding: const EdgeInsets.only(bottom: 8.0),
                                  child: Text('⚠️ $warning'),
                                )
                              ).toList(),
                            ),
                            actions: [
                              TextButton(
                                onPressed: () {
                                  orderProvider.clearStockWarnings();
                                  Navigator.of(context).pop();
                                },
                                child: const Text('Entendido'),
                              ),
                            ],
                          ),
                        );
                      }

                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Venta completada')),
                        );
                      }
                    }
                  } else {
                    // Para móvil, usar el flujo normal
                    try {
                      await orderProvider.closeOrdenConIva(metodoPago, config.tasaCambio, aplicaIva);
                      cartProvider.clear();

                      // Mostrar advertencias de stock si hay
                      if (orderProvider.stockWarnings.isNotEmpty && mounted) {
                        showDialog(
                          context: context,
                          builder: (context) => AlertDialog(
                            title: const Text('Advertencias de Stock'),
                            content: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: orderProvider.stockWarnings.map((warning) => 
                                Padding(
                                  padding: const EdgeInsets.only(bottom: 8.0),
                                  child: Text('⚠️ $warning'),
                                )
                              ).toList(),
                            ),
                            actions: [
                              TextButton(
                                onPressed: () {
                                  orderProvider.clearStockWarnings();
                                  Navigator.of(context).pop();
                                },
                                child: const Text('Entendido'),
                              ),
                            ],
                          ),
                        );
                      }

                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Venta completada')),
                        );
                      }
                    } catch (e) {
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Error: $e')),
                        );
                      }
                    }
                  }
                },
              ),
            ],
          );
        },
      ),
    );
  }
}
