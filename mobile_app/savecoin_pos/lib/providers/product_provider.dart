import 'package:flutter/foundation.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../models/producto.dart';
import '../services/sync_service.dart';

class ProductProvider with ChangeNotifier {
  final SyncService _syncService = SyncService();
  
  List<Producto> _productos = [];
  List<Producto> _filteredProductos = [];
  String? _selectedCategoria;
  bool _isLoading = false;
  String? _error;

  List<Producto> get productos => _filteredProductos;

  /// Todos los productos activos, sin el filtro de categoría ni de búsqueda del POS.
  List<Producto> get todosActivos => _productos.where((p) => p.activo).toList()
    ..sort((a, b) => a.nombre.toLowerCase().compareTo(b.nombre.toLowerCase()));
  String? get selectedCategoria => _selectedCategoria;
  bool get isLoading => _isLoading;
  String? get error => _error;

  List<String> get categorias {
    final cats = _productos.map((p) => p.categoria).whereType<String>().toSet().toList();
    cats.sort();
    return cats;
  }

  Future<void> loadProductos() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final productosBox = Hive.box<Producto>('productos');
      
      // Cargar desde Hive primero (offline)
      _productos = productosBox.values.toList();
      _applyFilter();
      
      // Intentar sincronizar si hay conexión
      await _syncService.syncProductos();
      
      // Recargar después de sincronizar
      _productos = productosBox.values.toList();
      _applyFilter();
      
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
    }
  }

  void filterByCategoria(String? categoria) {
    _selectedCategoria = categoria;
    _applyFilter();
    notifyListeners();
  }

  void searchProductos(String query) {
    if (query.isEmpty) {
      _applyFilter();
    } else {
      final q = query.toLowerCase();
      _filteredProductos = _productos.where((p) {
        return p.activo &&
            (p.nombre.toLowerCase().contains(q) ||
                p.codigo.toLowerCase().contains(q) ||
                (p.descripcion?.toLowerCase().contains(q) ?? false));
      }).toList();
    }
    notifyListeners();
  }

  void _applyFilter() {
    if (_selectedCategoria == null) {
      _filteredProductos = _productos.where((p) => p.activo).toList();
    } else {
      _filteredProductos = _productos
          .where((p) => p.activo && p.categoria == _selectedCategoria)
          .toList();
    }
  }

  Future<void> refresh() async {
    await loadProductos();
  }

  /// Vuelve a leer el catálogo desde Hive, sin llamar al servidor (se usa
  /// después de una sincronización que ya lo actualizó).
  void reloadLocal() {
    _productos = Hive.box<Producto>('productos').values.toList();
    _applyFilter();
    notifyListeners();
  }
}
