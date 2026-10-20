import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/bluetooth_provider.dart';
import '../providers/config_provider.dart';
import '../providers/order_provider.dart';

class ConfigScreen extends StatefulWidget {
  const ConfigScreen({super.key});

  @override
  State<ConfigScreen> createState() => _ConfigScreenState();
}

class _ConfigScreenState extends State<ConfigScreen> {
  bool _isCheckingConnection = false;
  bool _isConnected = false;
  bool _isSyncing = false;

  // Los controladores viven en el State. Antes se creaban dentro de build(), de
  // modo que cada reconstrucción reiniciaba el texto y el cursor mientras se
  // escribía (y cada letra guardaba la configuración).
  final _tasaController = TextEditingController();
  final _urlController = TextEditingController();
  bool _camposCargados = false;

  @override
  void dispose() {
    _tasaController.dispose();
    _urlController.dispose();
    super.dispose();
  }

  /// Rellena los campos una sola vez, cuando la configuración ya está cargada.
  void _cargarCampos(ConfigProvider provider) {
    final config = provider.config;
    if (_camposCargados || config == null) return;
    _camposCargados = true;
    _tasaController.text = config.tasaCambio.toString();
    _urlController.text = config.apiUrl;
  }

  Future<void> _checkConnection() async {
    setState(() => _isCheckingConnection = true);

    var connected = false;
    try {
      connected = await context.read<ConfigProvider>().checkConnection();
    } catch (_) {
      connected = false;
    }
    if (!mounted) return;
    setState(() {
      _isConnected = connected;
      _isCheckingConnection = false;
    });
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final config = context.read<ConfigProvider>();
      // Esta pantalla también se abre desde el inicio (sin turno), donde la
      // configuración todavía no se había cargado y quedaba un spinner infinito.
      if (config.config == null) config.loadConfig();
      _checkConnection();
    });
  }

  void _mostrar(String texto, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(texto), backgroundColor: error ? Colors.red : null),
    );
  }

  Future<void> _guardarTasa(ConfigProvider provider) async {
    final tasa = double.tryParse(_tasaController.text.trim().replaceAll(',', '.'));
    if (tasa == null || tasa <= 0) {
      _mostrar('Ingrese una tasa válida', error: true);
      return;
    }
    await provider.updateTasaCambio(tasa);
    if (mounted) _mostrar('Tasa guardada');
  }

  Future<void> _guardarUrl(ConfigProvider provider) async {
    final url = _urlController.text.trim();
    final valida = Uri.tryParse(url);
    if (valida == null || !valida.hasScheme || valida.host.isEmpty) {
      _mostrar('Ingrese una URL válida, por ejemplo http://192.168.1.100:8000/api',
          error: true);
      return;
    }
    await provider.updateApiUrl(url);
    if (mounted) await _checkConnection();
  }

  Future<void> _sincronizarTodo() async {
    setState(() => _isSyncing = true);
    try {
      final result = await context.read<OrderProvider>().syncService.manualSync();
      if (!mounted) return;
      _mostrar(result['message'] as String, error: result['success'] != true);
    } finally {
      if (mounted) setState(() => _isSyncing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Configuración'),
        backgroundColor: Colors.blue[700],
      ),
      body: Consumer3<ConfigProvider, BluetoothProvider, AuthProvider>(
        builder: (context, configProvider, bluetoothProvider, authProvider, _) {
          final config = configProvider.config;

          if (config == null) {
            if (configProvider.isLoading) {
              return const Center(child: CircularProgressIndicator());
            }
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(configProvider.error ?? 'No se pudo cargar la configuración'),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: configProvider.loadConfig,
                    child: const Text('Reintentar'),
                  ),
                ],
              ),
            );
          }
          _cargarCampos(configProvider);

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Tasa de Cambio
              _ConfigSection(
                title: 'Tasa de Cambio',
                icon: Icons.currency_exchange,
                child: Column(
                  children: [
                    TextField(
                      decoration: const InputDecoration(
                        labelText: 'USD a VES',
                        border: OutlineInputBorder(),
                        prefixText: '\$1 = Bs ',
                      ),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      controller: _tasaController,
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: () => _guardarTasa(configProvider),
                            icon: const Icon(Icons.save),
                            label: const Text('Guardar'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: () async {
                              await configProvider.syncFromServer();
                              if (!mounted) return;
                              final nueva = configProvider.config?.tasaCambio;
                              if (nueva != null) _tasaController.text = nueva.toString();
                              _mostrar('Tasa sincronizada del servidor');
                            },
                            icon: const Icon(Icons.sync),
                            label: const Text('Del Servidor'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Configuración API
              _ConfigSection(
                title: 'Configuración API',
                icon: Icons.api,
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            decoration: InputDecoration(
                              labelText: 'URL del Servidor',
                              border: const OutlineInputBorder(),
                              suffixIcon: _buildConnectionIcon(),
                            ),
                            controller: _urlController,
                            keyboardType: TextInputType.url,
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton(
                          icon: const Icon(Icons.save),
                          onPressed: () => _guardarUrl(configProvider),
                          tooltip: 'Guardar URL',
                        ),
                        IconButton(
                          icon: _isCheckingConnection
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.refresh),
                          onPressed: _isCheckingConnection ? null : _checkConnection,
                          tooltip: 'Verificar conexión',
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _getConnectionStatusText(),
                      style: TextStyle(
                        color: _isConnected ? Colors.green : Colors.red,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Impresora Bluetooth
              _ConfigSection(
                title: 'Impresora Bluetooth',
                icon: Icons.print,
                child: Column(
                  children: [
                    if (bluetoothProvider.isConnected)
                      Card(
                        color: Colors.green[50],
                        child: ListTile(
                          leading: const Icon(Icons.bluetooth_connected, color: Colors.green),
                          title: Text(bluetoothProvider.connectedDevice?.name ?? 'Desconocido'),
                          subtitle: Text(bluetoothProvider.connectedDevice?.id.toString() ?? ''),
                          trailing: IconButton(
                            icon: const Icon(Icons.bluetooth_disabled),
                            onPressed: () => bluetoothProvider.disconnect(),
                          ),
                        ),
                      )
                    else
                      Card(
                        color: Colors.orange[50],
                        child: const ListTile(
                          leading: Icon(Icons.bluetooth_disabled, color: Colors.orange),
                          title: Text('No conectado'),
                          subtitle: Text('Busque y conecte una impresora'),
                        ),
                      ),
                    const SizedBox(height: 12),
                    ElevatedButton.icon(
                      onPressed: () => _showBluetoothDialog(bluetoothProvider),
                      icon: const Icon(Icons.bluetooth),
                      label: const Text('Buscar Impresoras'),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Sincronización
              _ConfigSection(
                title: 'Sincronización',
                icon: Icons.sync,
                child: Column(
                  children: [
                    ElevatedButton.icon(
                      onPressed: _isSyncing ? null : _sincronizarTodo,
                      icon: _isSyncing
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.cloud_sync),
                      label: const Text('Sincronizar Todo'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.blue,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Cerrar Sesión
              _ConfigSection(
                title: 'Sesión',
                icon: Icons.logout,
                child: Column(
                  children: [
                    const Text(
                      '¿Deseas cerrar tu sesión actual?',
                      style: TextStyle(fontSize: 16),
                    ),
                    const SizedBox(height: 12),
                    ElevatedButton.icon(
                      onPressed: () async {
                        final confirmed = await showDialog<bool>(
                          context: context,
                          builder: (context) => AlertDialog(
                            title: const Text('Cerrar Sesión'),
                            content: const Text('¿Estás seguro de que deseas cerrar sesión?'),
                            actions: [
                              TextButton(
                                onPressed: () => Navigator.pop(context, false),
                                child: const Text('Cancelar'),
                              ),
                              TextButton(
                                onPressed: () => Navigator.pop(context, true),
                                style: TextButton.styleFrom(
                                  foregroundColor: Colors.red,
                                ),
                                child: const Text('Cerrar Sesión'),
                              ),
                            ],
                          ),
                        );

                        // logout() vuelve solo a la raíz de la app (AuthGate → login).
                        // La ruta '/login' no existe, por eso fallaba antes.
                        if (confirmed == true) await authProvider.logout();
                      },
                      icon: const Icon(Icons.logout),
                      label: const Text('Cerrar Sesión'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.red,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildConnectionIcon() {
    if (_isCheckingConnection) {
      return const SizedBox(
        width: 16,
        height: 16,
        child: CircularProgressIndicator(strokeWidth: 2),
      );
    }

    return Icon(
      _isConnected ? Icons.check_circle : Icons.error,
      color: _isConnected ? Colors.green : Colors.red,
      size: 20,
    );
  }

  String _getConnectionStatusText() {
    if (_isCheckingConnection) {
      return 'Verificando conexión...';
    }

    if (_isConnected) {
      return '✓ Conectado al servidor';
    }

    return '✗ No hay conexión con el servidor';
  }

  void _showBluetoothDialog(BluetoothProvider bluetoothProvider) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Seleccionar Impresora'),
        content: SizedBox(
          width: double.maxFinite,
          height: 400,
          child: Column(
            children: [
              ElevatedButton.icon(
                onPressed: () {
                  bluetoothProvider.startScan();
                },
                icon: const Icon(Icons.search),
                label: const Text('Escanear'),
              ),
              const SizedBox(height: 16),
              Expanded(
                child: Consumer<BluetoothProvider>(
                  builder: (context, provider, _) {
                    if (provider.isScanning) {
                      return const Center(child: CircularProgressIndicator());
                    }

                    if (provider.devices.isEmpty) {
                      return const Center(
                        child: Text('No se encontraron dispositivos'),
                      );
                    }

                    return ListView.builder(
                      itemCount: provider.devices.length,
                      itemBuilder: (context, index) {
                        final device = provider.devices[index];
                        return ListTile(
                          leading: const Icon(Icons.bluetooth),
                          title: Text(device.name),
                          subtitle: Text(device.id.toString()),
                          onTap: () async {
                            final success = await provider.connectToDevice(device);
                            if (success && context.mounted) {
                              Navigator.pop(context);
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Impresora conectada')),
                              );
                            }
                          },
                        );
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cerrar'),
          ),
        ],
      ),
    );
  }
}

class _ConfigSection extends StatelessWidget {
  final String title;
  final IconData icon;
  final Widget child;

  const _ConfigSection({
    required this.title,
    required this.icon,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: Colors.blue[700]),
                const SizedBox(width: 8),
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            child,
          ],
        ),
      ),
    );
  }
}
