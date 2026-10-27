import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../models/producto.dart';
import '../providers/product_provider.dart';
import '../services/api_service.dart';
import '../services/sync_service.dart';
import '../utils/log.dart';

/// Administración desde el teléfono (solo administradores): carga/descarga de inventario
/// y foto de los productos. El servidor vuelve a comprobar el rol en cada petición.
class AdminScreen extends StatelessWidget {
  const AdminScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Administración'),
          backgroundColor: Colors.blue[700],
          foregroundColor: Colors.white,
          bottom: const TabBar(
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white70,
            indicatorColor: Colors.white,
            tabs: [
              Tab(icon: Icon(Icons.inventory_2), text: 'Inventario'),
              Tab(icon: Icon(Icons.photo_camera), text: 'Fotos'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            _InventarioTab(),
            _FotosTab(),
          ],
        ),
      ),
    );
  }
}

double _num(dynamic v) => double.tryParse('${v ?? 0}') ?? 0;

/// 12.0000 -> 12, 2.5000 -> 2.5, 0.3333 -> 0.3333
String _fmt(double v) {
  var s = v.toStringAsFixed(4);
  if (s.contains('.')) {
    s = s.replaceAll(RegExp(r'0+$'), '').replaceAll(RegExp(r'\.$'), '');
  }
  return s;
}

// ---------------------------------------------------------------------------
// Inventario
// ---------------------------------------------------------------------------

class _InventarioTab extends StatefulWidget {
  const _InventarioTab();

  @override
  State<_InventarioTab> createState() => _InventarioTabState();
}

class _InventarioTabState extends State<_InventarioTab> {
  final _api = ApiService();
  List<Map<String, dynamic>> _insumos = [];
  String _busqueda = '';
  bool _cargando = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    setState(() {
      _cargando = true;
      _error = null;
    });
    try {
      final datos = await _api.getInsumosAdmin();
      if (!mounted) return;
      setState(() => _insumos = datos);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e is ApiException ? e.message : 'No se pudo cargar el inventario');
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  Future<void> _movimiento(Map<String, dynamic> insumo, String tipo) async {
    final actualizado = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _MovimientoDialog(insumo: insumo, tipo: tipo),
    );
    if (actualizado == null || !mounted) return;

    setState(() {
      final i = _insumos.indexWhere((e) => '${e['id']}' == '${actualizado['id']}');
      if (i >= 0) _insumos[i] = actualizado;
    });
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text('${actualizado['nombre']}: stock ${_fmt(_num(actualizado['stock_actual']))} ${actualizado['unidad_medida'] ?? ''}'),
      backgroundColor: Colors.green,
    ));
  }

  @override
  Widget build(BuildContext context) {
    final q = _busqueda.toLowerCase();
    final visibles = _insumos.where((i) {
      return q.isEmpty ||
          '${i['nombre']}'.toLowerCase().contains(q) ||
          '${i['codigo']}'.toLowerCase().contains(q);
    }).toList();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: TextField(
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Buscar insumo...',
              border: OutlineInputBorder(),
              isDense: true,
            ),
            onChanged: (v) => setState(() => _busqueda = v),
          ),
        ),
        Expanded(
          child: _cargando
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, textAlign: TextAlign.center),
                            const SizedBox(height: 12),
                            ElevatedButton.icon(
                              onPressed: _cargar,
                              icon: const Icon(Icons.refresh),
                              label: const Text('Reintentar'),
                            ),
                          ],
                        ),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _cargar,
                      child: visibles.isEmpty
                          ? ListView(children: const [
                              SizedBox(height: 80),
                              Center(child: Text('No hay insumos')),
                            ])
                          : ListView.separated(
                              itemCount: visibles.length,
                              separatorBuilder: (_, _) => const Divider(height: 1),
                              itemBuilder: (context, index) {
                                final i = visibles[index];
                                final stock = _num(i['stock_actual']);
                                final minimo = _num(i['stock_minimo']);
                                final bajo = stock <= minimo;
                                return ListTile(
                                  title: Text('${i['nombre']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                                  subtitle: Text(
                                    'Stock: ${_fmt(stock)} ${i['unidad_medida'] ?? ''}'
                                    '${bajo ? '  ·  stock bajo' : ''}',
                                    style: TextStyle(color: bajo ? Colors.red[700] : null),
                                  ),
                                  trailing: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      IconButton(
                                        tooltip: 'Descargar',
                                        icon: const Icon(Icons.remove_circle, color: Colors.red),
                                        onPressed: () => _movimiento(i, 'salida'),
                                      ),
                                      IconButton(
                                        tooltip: 'Cargar',
                                        icon: const Icon(Icons.add_circle, color: Colors.green),
                                        onPressed: () => _movimiento(i, 'entrada'),
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                    ),
        ),
      ],
    );
  }
}

class _MovimientoDialog extends StatefulWidget {
  final Map<String, dynamic> insumo;
  final String tipo; // entrada | salida

  const _MovimientoDialog({required this.insumo, required this.tipo});

  @override
  State<_MovimientoDialog> createState() => _MovimientoDialogState();
}

class _MovimientoDialogState extends State<_MovimientoDialog> {
  final _cantidad = TextEditingController();
  final _motivo = TextEditingController();
  bool _enviando = false;
  String? _error;

  bool get _esEntrada => widget.tipo == 'entrada';

  List<String> get _motivosRapidos =>
      _esEntrada ? ['Compra', 'Devolución', 'Corrección'] : ['Merma', 'Consumo interno', 'Corrección'];

  @override
  void dispose() {
    _cantidad.dispose();
    _motivo.dispose();
    super.dispose();
  }

  double? get _valor => double.tryParse(_cantidad.text.trim().replaceAll(',', '.'));

  bool get _valido => (_valor ?? 0) > 0 && _motivo.text.trim().isNotEmpty;

  Future<void> _enviar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final actualizado = await ApiService().registrarMovimiento(
        insumoId: '${widget.insumo['id']}',
        tipo: widget.tipo,
        cantidad: _valor!,
        motivo: _motivo.text.trim(),
      );
      if (mounted) Navigator.pop(context, actualizado);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _enviando = false;
        _error = e is ApiException ? e.message : 'No se pudo registrar el movimiento';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final unidad = '${widget.insumo['unidad_medida'] ?? ''}';
    return AlertDialog(
      title: Text(_esEntrada ? 'Cargar inventario' : 'Descargar inventario'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${widget.insumo['nombre']}', style: const TextStyle(fontWeight: FontWeight.bold)),
            Text('Stock actual: ${_fmt(_num(widget.insumo['stock_actual']))} $unidad'),
            const SizedBox(height: 16),
            TextField(
              controller: _cantidad,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                labelText: _esEntrada ? 'Cantidad a cargar' : 'Cantidad a descargar',
                suffixText: unidad,
                border: const OutlineInputBorder(),
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              children: [
                for (final m in _motivosRapidos)
                  ActionChip(
                    label: Text(m),
                    onPressed: () => setState(() => _motivo.text = m),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _motivo,
              decoration: const InputDecoration(
                labelText: 'Motivo (obligatorio)',
                border: OutlineInputBorder(),
              ),
              onChanged: (_) => setState(() {}),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _enviando ? null : () => Navigator.pop(context),
          child: const Text('Cancelar'),
        ),
        ElevatedButton(
          onPressed: _valido && !_enviando ? _enviar : null,
          style: ElevatedButton.styleFrom(
            backgroundColor: _esEntrada ? Colors.green : Colors.red,
            foregroundColor: Colors.white,
          ),
          child: _enviando
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : Text(_esEntrada ? 'Cargar' : 'Descargar'),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Fotos de productos
// ---------------------------------------------------------------------------

class _FotosTab extends StatefulWidget {
  const _FotosTab();

  @override
  State<_FotosTab> createState() => _FotosTabState();
}

class _FotosTabState extends State<_FotosTab> {
  final _api = ApiService();
  final _picker = ImagePicker();
  final Set<String> _ocupados = {};
  String _busqueda = '';
  bool _cargando = true;

  @override
  void initState() {
    super.initState();
    // Después del primer frame: loadProductos() notifica a los listeners y no se puede
    // hacerlo mientras el árbol de widgets se está construyendo.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _cargar();
    });
  }

  Future<void> _cargar() async {
    setState(() => _cargando = true);
    // Sincroniza el catálogo para ver las fotos más recientes
    await context.read<ProductProvider>().loadProductos();
    if (mounted) setState(() => _cargando = false);
  }

  void _aviso(String texto, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(texto), backgroundColor: error ? Colors.red : Colors.green),
    );
  }

  Future<void> _opciones(Producto p) async {
    final tieneFoto = p.imagenUrl != null && p.imagenUrl!.isNotEmpty;
    final accion = await showModalBottomSheet<String>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(title: Text(p.nombre, style: const TextStyle(fontWeight: FontWeight.bold))),
            ListTile(
              leading: const Icon(Icons.photo_camera),
              title: const Text('Tomar foto'),
              onTap: () => Navigator.pop(sheetContext, 'camara'),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Elegir de la galería'),
              onTap: () => Navigator.pop(sheetContext, 'galeria'),
            ),
            if (tieneFoto)
              ListTile(
                leading: const Icon(Icons.delete, color: Colors.red),
                title: const Text('Quitar foto'),
                onTap: () => Navigator.pop(sheetContext, 'quitar'),
              ),
          ],
        ),
      ),
    );
    if (accion == null || !mounted) return;

    if (accion == 'quitar') {
      await _ejecutar(p, () => _api.eliminarImagenProducto(p.id), 'Foto eliminada');
      return;
    }

    // La foto se reduce antes de enviarla: las del teléfono pesan varios MB
    XFile? foto;
    try {
      foto = await _picker.pickImage(
        source: accion == 'camara' ? ImageSource.camera : ImageSource.gallery,
        maxWidth: 1000,
        maxHeight: 1000,
        imageQuality: 80,
      );
    } catch (e) {
      logd('No se pudo abrir la cámara/galería: $e');
      if (mounted) _aviso('No se pudo abrir la cámara o la galería', error: true);
      return;
    }
    if (foto == null || !mounted) return;

    final bytes = await foto.readAsBytes();
    final nombre = foto.name.contains('.') ? foto.name : 'foto.jpg';
    await _ejecutar(p, () => _api.subirImagenProducto(p.id, bytes, nombre), 'Foto guardada');
  }

  Future<void> _ejecutar(Producto p, Future<dynamic> Function() accion, String exito) async {
    setState(() => _ocupados.add(p.id));
    try {
      await accion();
      // Actualiza el catálogo local para que el POS muestre la foto nueva
      await SyncService().syncProductos();
      if (!mounted) return;
      context.read<ProductProvider>().reloadLocal();
      _aviso(exito);
    } catch (e) {
      if (mounted) _aviso(e is ApiException ? e.message : 'No se pudo completar la acción', error: true);
    } finally {
      if (mounted) setState(() => _ocupados.remove(p.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final q = _busqueda.toLowerCase();
    final productos = context.watch<ProductProvider>().todosActivos.where((p) {
      return q.isEmpty || p.nombre.toLowerCase().contains(q) || p.codigo.toLowerCase().contains(q);
    }).toList();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: TextField(
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Buscar producto...',
              border: OutlineInputBorder(),
              isDense: true,
            ),
            onChanged: (v) => setState(() => _busqueda = v),
          ),
        ),
        Expanded(
          child: _cargando
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _cargar,
                  child: productos.isEmpty
                      ? ListView(children: const [
                          SizedBox(height: 80),
                          Center(child: Text('No hay productos')),
                        ])
                      : ListView.separated(
                          itemCount: productos.length,
                          separatorBuilder: (_, _) => const Divider(height: 1),
                          itemBuilder: (context, index) {
                            final p = productos[index];
                            final ocupado = _ocupados.contains(p.id);
                            return ListTile(
                              leading: _Miniatura(url: p.imagenUrl),
                              title: Text(p.nombre, style: const TextStyle(fontWeight: FontWeight.w600)),
                              subtitle: Text(p.categoria ?? 'Sin categoría'),
                              trailing: ocupado
                                  ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Icon(Icons.photo_camera),
                              onTap: ocupado ? null : () => _opciones(p),
                            );
                          },
                        ),
                ),
        ),
      ],
    );
  }
}

class _Miniatura extends StatelessWidget {
  final String? url;

  const _Miniatura({required this.url});

  @override
  Widget build(BuildContext context) {
    const tam = 56.0;
    final vacia = Container(
      width: tam,
      height: tam,
      decoration: BoxDecoration(color: Colors.grey[200], borderRadius: BorderRadius.circular(8)),
      child: const Icon(Icons.restaurant, color: Colors.grey),
    );
    if (url == null || url!.isEmpty) return vacia;

    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: CachedNetworkImage(
        imageUrl: url!,
        width: tam,
        height: tam,
        fit: BoxFit.cover,
        placeholder: (_, _) => vacia,
        errorWidget: (_, _, _) => vacia,
      ),
    );
  }
}
