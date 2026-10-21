import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/cliente.dart';
import '../models/detalle_orden.dart';
import '../providers/cart_provider.dart';
import '../providers/client_provider.dart';
import '../providers/config_provider.dart';
import '../providers/order_provider.dart';
import '../providers/product_provider.dart';
import '../providers/shift_provider.dart';
import '../services/api_service.dart';
import '../services/bluetooth_service.dart';
import '../utils/log.dart';
import '../widgets/cart_panel.dart';
import '../widgets/category_sidebar.dart';
import '../widgets/order_widgets.dart';
import '../widgets/product_card.dart';
import 'client_selector_screen.dart';
import 'closed_orders_screen.dart';
import 'config_screen.dart';
import 'open_orders_screen.dart';

class POSScreen extends StatefulWidget {
  const POSScreen({super.key});

  @override
  State<POSScreen> createState() => _POSScreenState();
}

class _POSScreenState extends State<POSScreen> {
  int _stockBajoCount = 0;

  /// true mientras se guarda o cobra una orden (evita toques dobles).
  bool _busy = false;

  /// Cliente que se usa al cobrar sin haber elegido uno.
  static final Cliente _clienteGeneral =
      Cliente(id: '', nombre: 'General', apellido: '', cedula: '');

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProductProvider>().loadProductos();
      context.read<ConfigProvider>().loadConfig();
      context.read<OrderProvider>().loadOrdenes();
      context.read<ClientProvider>().loadClientes();
      _loadStockAlerts();
    });
  }

  Future<void> _loadStockAlerts() async {
    try {
      final alerts = await ApiService().getStockAlerts();
      final counters = alerts['counters'];
      final bajo = counters is Map ? counters['stock_bajo'] : null;
      if (mounted) setState(() => _stockBajoCount = bajo is num ? bajo.toInt() : 0);
    } catch (e) {
      logd('No se pudieron cargar las alertas de stock: $e');
    }
  }

  double get _tasaCambio => context.read<ConfigProvider>().config?.tasaCambio ?? 1;

  /// Sin la configuración (tasa de cambio) no se puede cobrar ni imprimir:
  /// la tasa quedaría grabada mal en la orden.
  bool _configLista() {
    if (context.read<ConfigProvider>().config != null) return true;
    _aviso('La configuración aún no se ha cargado. Inténtelo de nuevo en un momento.',
        error: true);
    return false;
  }

  void _aviso(String texto, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(texto), backgroundColor: error ? Colors.red : null),
    );
  }

  /// Convierte el carrito en detalles de la orden en edición (o de una nueva).
  List<DetalleOrden> _detallesDelCarrito() {
    final orderId = context.read<OrderProvider>().currentOrden?.id ?? '';
    return context.read<CartProvider>().toDetallesOrden(orderId);
  }

  /// Ejecuta una acción larga con los botones bloqueados y muestra el resultado.
  /// El carrito solo se vacía si la acción terminó bien: si falla, el pedido no
  /// se pierde.
  Future<void> _ejecutar(Future<AccionResultado> Function() accion,
      {bool limpiarCarrito = false}) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final resultado = await accion();
      if (!mounted) return;
      if (resultado.ok && limpiarCarrito) context.read<CartProvider>().clear();
      await mostrarResultado(context, resultado, tasaCambio: _tasaCambio);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<String?> _pedirNotas() {
    return showDialog<String>(
      context: context,
      builder: (_) => const _NotasDialog(),
    );
  }

  Future<void> _enviarACocina() async {
    final orders = context.read<OrderProvider>();
    final cart = context.read<CartProvider>();
    final cliente = orders.selectedCliente;

    if (!_configLista()) return;
    if (cliente == null) {
      _aviso('Debe seleccionar un cliente antes de enviar la comanda', error: true);
      return;
    }

    // null = el usuario cerró el diálogo: no se envía nada
    final notas = await _pedirNotas();
    if (notas == null || !mounted) return;

    await _ejecutar(
      () => orders.enviarComanda(
        detalles: _detallesDelCarrito(),
        cliente: cliente,
        tasaCambio: _tasaCambio,
        aplicaIva: cart.aplicaIva,
        notas: notas,
      ),
      limpiarCarrito: true,
    );
  }

  Future<void> _imprimirPreCuenta() async {
    if (!_configLista()) return;
    final orders = context.read<OrderProvider>();
    final cart = context.read<CartProvider>();
    await _ejecutar(() => orders.imprimirPreCuenta(
          detalles: _detallesDelCarrito(),
          cliente: orders.selectedCliente,
          tasaCambio: _tasaCambio,
          aplicaIva: cart.aplicaIva,
        ));
  }

  Future<void> _cobrar(String metodoPago, bool aplicaIva,
      {double? montoEntregado, String? referencia}) async {
    if (!_configLista()) return;
    final orders = context.read<OrderProvider>();
    await _ejecutar(
      () => orders.cobrar(
        detalles: _detallesDelCarrito(),
        cliente: orders.selectedCliente ?? _clienteGeneral,
        tasaCambio: _tasaCambio,
        metodoPago: metodoPago,
        aplicaIva: aplicaIva,
        montoEntregado: montoEntregado,
        referencia: referencia,
      ),
      limpiarCarrito: true,
    );
  }

  Future<void> _sincronizar() async {
    final result = await context.read<OrderProvider>().syncService.manualSync();
    if (!mounted) return;
    _aviso(result['message'] as String, error: result['success'] != true);
    // Lo sincronizado cambia las listas de órdenes
    context.read<OrderProvider>().loadOrdenes();
  }

  Future<void> _cerrarTurno() async {
    final shiftProvider = context.read<ShiftProvider>();
    // El ScaffoldMessenger vive en MaterialApp: sigue disponible aunque esta pantalla
    // se cierre mientras se imprime el reporte.
    final messenger = ScaffoldMessenger.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cerrar Turno'),
        content: const Text(
            '¿Está seguro de cerrar el turno? Esto consolidará las ventas en el libro contable.'),
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
    if (confirmed != true || !mounted) return;

    final success = await shiftProvider.closeShift();
    if (!mounted) return;
    if (success) {
      context.read<CartProvider>().clear();
      context.read<OrderProvider>().clearCurrentOrden();
      // No hay que navegar: al cerrarse el turno, AuthGate muestra la pantalla de inicio
      final resumen = shiftProvider.lastClosingSummary;
      var avisoImpresion = '';
      if (resumen != null) {
        try {
          await BluetoothService().printCierreTurno(resumen);
        } catch (e) {
          final texto = e.toString();
          if (!texto.contains('WEB_SIMULATION')) {
            avisoImpresion = ' (no se pudo imprimir el reporte: impresora no disponible)';
          }
        }
      }
      messenger.showSnackBar(SnackBar(
        content: Text('Turno cerrado exitosamente$avisoImpresion'),
        backgroundColor: avisoImpresion.isNotEmpty ? Colors.orange[800] : null,
      ));
    } else {
      _aviso('Error al cerrar turno', error: true);
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
                  onPressed: () => showDialog<void>(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text('Alertas de Stock'),
                      content: Text(
                          'Hay $_stockBajoCount insumos con stock bajo.\n\nVerifica el inventario en el sistema web.'),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(context),
                          child: const Text('Entendido'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          IconButton(
            icon: const Icon(Icons.sync),
            tooltip: 'Sincronizar',
            onPressed: _sincronizar,
          ),
          IconButton(
            icon: const Icon(Icons.receipt_long),
            tooltip: 'Órdenes Abiertas',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const OpenOrdersScreen()),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.archive),
            tooltip: 'Órdenes Cerradas',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const ClosedOrdersScreen()),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close),
            tooltip: 'Cerrar Turno',
            onPressed: _cerrarTurno,
          ),
          IconButton(
            icon: const Icon(Icons.settings),
            tooltip: 'Configuración',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const ConfigScreen()),
            ),
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
                onCategorySelected: productProvider.filterByCategoria,
              ),

              // Grid de Productos (Centro)
              Expanded(
                flex: 3,
                child: Container(
                  color: Colors.grey[100],
                  child: Column(
                    children: [
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
                          onChanged: productProvider.searchProductos,
                        ),
                      ),
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
                                    gridDelegate:
                                        const SliverGridDelegateWithFixedCrossAxisCount(
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
                                        onTap: () => cartProvider.addItem(producto),
                                      );
                                    },
                                  ),
                      ),
                    ],
                  ),
                ),
              ),

              // Panel del Carrito (Derecha). Los cambios del carrito no se
              // guardan ni se imprimen hasta "Enviar a Cocina" o "Cobrar".
              CartPanel(
                cartItems: cartProvider.items,
                subtotal: cartProvider.subtotal,
                impuesto: cartProvider.impuesto,
                total: cartProvider.total,
                itemCount: cartProvider.itemCount,
                aplicaIva: cartProvider.aplicaIva,
                selectedCliente: orderProvider.selectedCliente,
                ordenEnEdicion: orderProvider.currentOrden?.numeroRecibo,
                busy: _busy,
                onClienteSelected: () async {
                  final cliente = await Navigator.push<Cliente?>(
                    context,
                    MaterialPageRoute(builder: (_) => const ClientSelectorScreen()),
                  );
                  if (cliente != null) orderProvider.selectCliente(cliente);
                },
                onClearCart: () {
                  cartProvider.clear();
                  orderProvider.clearCurrentOrden();
                },
                onRemoveItem: cartProvider.removeItem,
                onUpdateCantidad: cartProvider.updateCantidad,
                onAplicaIvaChanged: cartProvider.setAplicaIva,
                onSendToKitchen: _enviarACocina,
                onPrintPreCuenta: _imprimirPreCuenta,
                onCobrar: _cobrar,
              ),
            ],
          );
        },
      ),
    );
  }
}

/// Diálogo de notas del cliente. Tiene su propio controlador: antes el texto
/// se guardaba en una variable capturada por el builder y "Aceptar" devolvía
/// el valor equivocado.
class _NotasDialog extends StatefulWidget {
  const _NotasDialog();

  @override
  State<_NotasDialog> createState() => _NotasDialogState();
}

class _NotasDialogState extends State<_NotasDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Notas del Cliente'),
      content: TextField(
        controller: _controller,
        autofocus: true,
        decoration: const InputDecoration(
          hintText: 'Ej: Sin cebolla, Término medio, etc.',
          border: OutlineInputBorder(),
        ),
        maxLines: 3,
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancelar'),
        ),
        TextButton(
          onPressed: () => Navigator.pop(context, ''),
          child: const Text('Omitir'),
        ),
        ElevatedButton(
          onPressed: () => Navigator.pop(context, _controller.text),
          child: const Text('Aceptar'),
        ),
      ],
    );
  }
}
