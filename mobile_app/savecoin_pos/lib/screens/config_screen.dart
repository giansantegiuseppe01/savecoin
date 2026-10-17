import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/config_provider.dart';
import '../providers/bluetooth_provider.dart';
import '../providers/auth_provider.dart';
import 'package:flutter_blue_plus/flutter_blue_plus.dart';

class ConfigScreen extends StatefulWidget {
  const ConfigScreen({super.key});

  @override
  State<ConfigScreen> createState() => _ConfigScreenState();
}

class _ConfigScreenState extends State<ConfigScreen> {
  bool _isCheckingConnection = false;
  bool _isConnected = false;

  Future<void> _checkConnection() async {
    setState(() => _isCheckingConnection = true);

    try {
      final connected = await Provider.of<ConfigProvider>(context, listen: false)
          .checkConnection();
      setState(() => _isConnected = connected);
    } catch (e) {
      setState(() => _isConnected = false);
    } finally {
      setState(() => _isCheckingConnection = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _checkConnection();
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
            return const Center(child: CircularProgressIndicator());
          }

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
                      controller: TextEditingController(
                        text: config.tasaCambio.toString(),
                      ),
                      onChanged: (value) {
                        final tasa = double.tryParse(value);
                        if (tasa != null && tasa > 0) {
                          configProvider.updateTasaCambio(tasa);
                        }
                      },
                    ),
                    const SizedBox(height: 8),
                    ElevatedButton.icon(
                      onPressed: () async {
                        await configProvider.syncFromServer();
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Tasa sincronizada del servidor')),
                          );
                        }
                      },
                      icon: const Icon(Icons.sync),
                      label: const Text('Sincronizar del Servidor'),
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
                              border: OutlineInputBorder(),
                              suffixIcon: _buildConnectionIcon(),
                            ),
                            controller: TextEditingController(
                              text: config.apiUrl,
                            ),
                            onChanged: (value) {
                              configProvider.updateApiUrl(value);
                            },
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton(
                          icon: _isCheckingConnection
                              ? const CircularProgressIndicator(strokeWidth: 2)
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
                    const SizedBox(height: 12),
                    TextField(
                      decoration: const InputDecoration(
                        labelText: 'Token de API (opcional)',
                        border: OutlineInputBorder(),
                      ),
                      controller: TextEditingController(
                        text: config.apiToken,
                      ),
                      onChanged: (value) {
                        configProvider.updateApiToken(value);
                      },
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
                      onPressed: () async {
                        final syncService = configProvider;
                        // Aquí se implementaría la sincronización completa
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Sincronización iniciada...')),
                          );
                        }
                      },
                      icon: const Icon(Icons.cloud_sync),
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

                        if (confirmed == true && mounted) {
                          await authProvider.logout();
                          if (mounted) {
                            Navigator.of(context).pushNamedAndRemoveUntil(
                              '/login',
                              (route) => false,
                            );
                          }
                        }
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
                            if (success && mounted) {
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
