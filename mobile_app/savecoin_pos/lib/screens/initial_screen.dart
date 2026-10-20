import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/shift_provider.dart';
import '../providers/auth_provider.dart';
import 'config_screen.dart';

class InitialScreen extends StatelessWidget {
  const InitialScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Colors.blue[700]!, Colors.blue[900]!],
          ),
        ),
        child: Center(
          child: Consumer<ShiftProvider>(
            builder: (context, shiftProvider, _) {
              return Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.point_of_sale,
                    size: 120,
                    color: Colors.white,
                  ),
                  const SizedBox(height: 24),
                  const Text(
                    'SaveCoin POS',
                    style: TextStyle(
                      fontSize: 36,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Usuario: ${context.watch<AuthProvider>().userName ?? "No autenticado"}',
                    style: const TextStyle(
                      fontSize: 18,
                      color: Colors.white70,
                    ),
                  ),
                  const SizedBox(height: 64),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      _buildActionButton(
                        context,
                        icon: Icons.settings,
                        label: 'Configurar',
                        color: Colors.orange,
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => const ConfigScreen()),
                          );
                        },
                      ),
                      const SizedBox(width: 32),
                      _buildActionButton(
                        context,
                        icon: Icons.play_circle,
                        label: 'Abrir Turno',
                        color: Colors.green,
                        onTap: () async {
                          final success = await shiftProvider.openShift();
                          if (success && context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Turno abierto exitosamente'),
                                backgroundColor: Colors.green,
                              ),
                            );
                          } else if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Error al abrir turno'),
                                backgroundColor: Colors.red,
                              ),
                            );
                          }
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 32),
                  TextButton.icon(
                    onPressed: () => context.read<AuthProvider>().logout(),
                    icon: const Icon(Icons.logout, color: Colors.white70),
                    label: const Text(
                      'Cerrar sesión',
                      style: TextStyle(color: Colors.white70),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildActionButton(
    BuildContext context, {
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Card(
      elevation: 8,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: 180,
          height: 180,
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [color, color.withValues(alpha: 0.8)],
            ),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 64,
                color: Colors.white,
              ),
              const SizedBox(height: 16),
              Text(
                label,
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
