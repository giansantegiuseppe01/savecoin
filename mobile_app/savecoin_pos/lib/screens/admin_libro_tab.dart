import 'package:flutter/material.dart';
import '../services/api_service.dart';

/// Libro contable desde el teléfono (solo administradores): saldo, últimos movimientos
/// y registro de entradas/salidas. Los montos están en bolívares, igual que en la web.
class LibroContableTab extends StatefulWidget {
  const LibroContableTab({super.key});

  @override
  State<LibroContableTab> createState() => _LibroContableTabState();
}

double _num(dynamic v) => double.tryParse('${v ?? 0}') ?? 0;

/// 1234567.8 -> 1,234,567.80
String _bs(double v) {
  final negativo = v < 0;
  final partes = v.abs().toStringAsFixed(2).split('.');
  final entero = partes[0].replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => ',');
  return '${negativo ? '-' : ''}$entero.${partes[1]}';
}

/// 2026-11-02 -> 02/11/2026
String _fecha(String iso) {
  final p = iso.split('-');
  return p.length == 3 ? '${p[2]}/${p[1]}/${p[0]}' : iso;
}

String _aIso(DateTime d) =>
    '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

class _LibroContableTabState extends State<LibroContableTab> {
  final _api = ApiService();
  Map<String, dynamic>? _datos;
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
      final datos = await _api.getLibroContable();
      if (!mounted) return;
      setState(() => _datos = datos);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e is ApiException ? e.message : 'No se pudo cargar el libro contable');
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  List<Map<String, dynamic>> get _categorias =>
      ((_datos?['categorias'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();

  Future<void> _nuevo() async {
    final registrado = await showDialog<bool>(
      context: context,
      builder: (_) => _NuevoMovimientoDialog(categorias: _categorias),
    );
    if (registrado != true || !mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Movimiento registrado'), backgroundColor: Colors.green),
    );
    await _cargar();
  }

  @override
  Widget build(BuildContext context) {
    final movimientos = ((_datos?['movimientos'] as List?) ?? [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: _datos == null
          ? null
          : FloatingActionButton.extended(
              onPressed: _nuevo,
              icon: const Icon(Icons.add),
              label: const Text('Movimiento'),
            ),
      body: _cargando && _datos == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _datos == null
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
                  child: ListView(
                    padding: const EdgeInsets.only(bottom: 88),
                    children: [
                      _Resumen(datos: _datos!),
                      if (movimientos.isEmpty)
                        const Padding(
                          padding: EdgeInsets.all(32),
                          child: Center(child: Text('Todavía no hay movimientos')),
                        ),
                      for (final m in movimientos) _FilaMovimiento(m: m),
                    ],
                  ),
                ),
    );
  }
}

class _Resumen extends StatelessWidget {
  final Map<String, dynamic> datos;

  const _Resumen({required this.datos});

  @override
  Widget build(BuildContext context) {
    final saldo = _num(datos['saldo_actual']);
    return Card(
      margin: const EdgeInsets.all(12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            const Text('Saldo actual', style: TextStyle(color: Colors.grey)),
            Text(
              'Bs ${_bs(saldo)}',
              style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.bold,
                color: saldo < 0 ? Colors.red[700] : null,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _Total(
                    titulo: 'Entradas',
                    monto: _num(datos['total_entradas']),
                    color: Colors.green[700]!,
                  ),
                ),
                Expanded(
                  child: _Total(
                    titulo: 'Salidas',
                    monto: _num(datos['total_salidas']),
                    color: Colors.red[700]!,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Total extends StatelessWidget {
  final String titulo;
  final double monto;
  final Color color;

  const _Total({required this.titulo, required this.monto, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(titulo, style: const TextStyle(color: Colors.grey)),
        Text('Bs ${_bs(monto)}', style: TextStyle(fontWeight: FontWeight.w600, color: color)),
      ],
    );
  }
}

class _FilaMovimiento extends StatelessWidget {
  final Map<String, dynamic> m;

  const _FilaMovimiento({required this.m});

  @override
  Widget build(BuildContext context) {
    final entrada = m['tipo_movimiento'] == 'entrada';
    final color = entrada ? Colors.green[700]! : Colors.red[700]!;
    final detalle = [
      _fecha('${m['fecha']}'),
      if (m['categoria'] != null) '${m['categoria']}',
      if (m['referencia'] != null && '${m['referencia']}'.isNotEmpty) 'Ref. ${m['referencia']}',
    ].join(' · ');

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: color.withValues(alpha: 0.12),
        child: Icon(entrada ? Icons.arrow_downward : Icons.arrow_upward, color: color),
      ),
      title: Text('${m['descripcion']}', maxLines: 2, overflow: TextOverflow.ellipsis),
      subtitle: Text(detalle),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            '${entrada ? '+' : '-'}${_bs(_num(m['monto']))}',
            style: TextStyle(fontWeight: FontWeight.bold, color: color),
          ),
          Text('Saldo ${_bs(_num(m['saldo_despues']))}',
              style: const TextStyle(fontSize: 11, color: Colors.grey)),
        ],
      ),
    );
  }
}

class _NuevoMovimientoDialog extends StatefulWidget {
  final List<Map<String, dynamic>> categorias;

  const _NuevoMovimientoDialog({required this.categorias});

  @override
  State<_NuevoMovimientoDialog> createState() => _NuevoMovimientoDialogState();
}

class _NuevoMovimientoDialogState extends State<_NuevoMovimientoDialog> {
  final _monto = TextEditingController();
  final _descripcion = TextEditingController();
  final _referencia = TextEditingController();
  String _tipo = 'entrada';
  int? _categoriaId;
  DateTime _fecha = DateTime.now();
  bool _enviando = false;
  String? _error;

  @override
  void dispose() {
    _monto.dispose();
    _descripcion.dispose();
    _referencia.dispose();
    super.dispose();
  }

  double? get _valor => double.tryParse(_monto.text.trim().replaceAll(',', '.'));

  bool get _valido => (_valor ?? 0) > 0 && _descripcion.text.trim().isNotEmpty;

  Future<void> _elegirFecha() async {
    final hoy = DateTime.now();
    final elegida = await showDatePicker(
      context: context,
      initialDate: _fecha,
      firstDate: DateTime(hoy.year - 2),
      lastDate: hoy,
    );
    if (elegida != null) setState(() => _fecha = elegida);
  }

  Future<void> _enviar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      await ApiService().registrarMovimientoLibro(
        fecha: _aIso(_fecha),
        tipo: _tipo,
        monto: _valor!,
        descripcion: _descripcion.text.trim(),
        referencia: _referencia.text.trim(),
        categoriaId: _categoriaId,
      );
      if (mounted) Navigator.pop(context, true);
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
    final esEntrada = _tipo == 'entrada';
    return AlertDialog(
      scrollable: true,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
      title: const Text('Nuevo movimiento'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'entrada', label: Text('Entrada'), icon: Icon(Icons.arrow_downward)),
                ButtonSegment(value: 'salida', label: Text('Salida'), icon: Icon(Icons.arrow_upward)),
              ],
              selected: {_tipo},
              onSelectionChanged: _enviando ? null : (s) => setState(() => _tipo = s.first),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _monto,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'Monto',
                prefixText: 'Bs ',
                border: OutlineInputBorder(),
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _descripcion,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Descripción (obligatoria)',
                border: OutlineInputBorder(),
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<int?>(
              initialValue: _categoriaId,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Categoría (opcional)', border: OutlineInputBorder()),
              items: [
                const DropdownMenuItem<int?>(value: null, child: Text('Sin categoría')),
                for (final c in widget.categorias)
                  DropdownMenuItem<int?>(value: int.tryParse('${c['id']}'), child: Text('${c['nombre']}')),
              ],
              onChanged: (v) => setState(() => _categoriaId = v),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _referencia,
              decoration: const InputDecoration(
                labelText: 'Referencia (opcional)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 8),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.calendar_today),
              title: Text('Fecha: ${_fecha.day.toString().padLeft(2, '0')}/${_fecha.month.toString().padLeft(2, '0')}/${_fecha.year}'),
              trailing: const Icon(Icons.edit),
              onTap: _enviando ? null : _elegirFecha,
            ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _enviando ? null : () => Navigator.pop(context, false),
          child: const Text('Cancelar'),
        ),
        ElevatedButton(
          onPressed: _valido && !_enviando ? _enviar : null,
          style: ElevatedButton.styleFrom(
            backgroundColor: esEntrada ? Colors.green : Colors.red,
            foregroundColor: Colors.white,
          ),
          child: _enviando
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : Text(esEntrada ? 'Registrar entrada' : 'Registrar salida'),
        ),
      ],
    );
  }
}
