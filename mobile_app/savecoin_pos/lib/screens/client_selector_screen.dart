import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/cliente.dart';
import '../providers/client_provider.dart';

class ClientSelectorScreen extends StatefulWidget {
  const ClientSelectorScreen({super.key});

  @override
  State<ClientSelectorScreen> createState() => _ClientSelectorScreenState();
}

class _ClientSelectorScreenState extends State<ClientSelectorScreen> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _searchController.addListener(_filterClientes);
    // Load clients when screen opens
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ClientProvider>().loadClientes();
    });
  }

  void _filterClientes() {
    final query = _searchController.text;
    context.read<ClientProvider>().searchClientes(query);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Seleccionar Cliente'),
        backgroundColor: Colors.blue[700],
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              context.read<ClientProvider>().syncClientes();
            },
            tooltip: 'Sincronizar clientes',
          ),
        ],
      ),
      body: Column(
        children: [
          // Barra de búsqueda
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Buscar cliente por nombre, cédula, teléfono...',
                prefixIcon: const Icon(Icons.search),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                filled: true,
                fillColor: Colors.white,
              ),
            ),
          ),
          
          // Lista de clientes
          Expanded(
            child: Consumer<ClientProvider>(
              builder: (context, clientProvider, child) {
                if (clientProvider.isLoading) {
                  return const Center(child: CircularProgressIndicator());
                }
                
                if (clientProvider.clientes.isEmpty) {
                  return const Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.person_search, size: 64, color: Colors.grey),
                        SizedBox(height: 16),
                        Text('No hay clientes disponibles'),
                      ],
                    ),
                  );
                }
                
                return ListView.builder(
                  itemCount: clientProvider.clientes.length,
                  itemBuilder: (context, index) {
                    final cliente = clientProvider.clientes[index];
                    return ListTile(
                      leading: CircleAvatar(
                        backgroundColor: Colors.blue[100],
                        child: Text(
                          (cliente.nombre.isEmpty ? '?' : cliente.nombre[0]).toUpperCase(),
                          style: TextStyle(color: Colors.blue[700]),
                        ),
                      ),
                      title: Text(
                        cliente.nombreCompleto,
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      subtitle: Text('CI: ${cliente.cedula}'),
                      trailing: cliente.isLocal 
                          ? const Icon(Icons.cloud_off, color: Colors.orange, size: 16)
                          : const Icon(Icons.cloud_done, color: Colors.green, size: 16),
                      onTap: () {
                        Navigator.pop(context, cliente);
                      },
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          _showAddClienteDialog();
        },
        backgroundColor: Colors.blue[700],
        child: const Icon(Icons.add),
      ),
    );
  }

  void _showAddClienteDialog() {
    final nombreController = TextEditingController();
    final apellidoController = TextEditingController();
    final cedulaController = TextEditingController();
    final telefonoController = TextEditingController();
    final correoController = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nuevo Cliente'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nombreController,
                decoration: const InputDecoration(
                  labelText: 'Nombre *',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: apellidoController,
                decoration: const InputDecoration(
                  labelText: 'Apellido *',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: cedulaController,
                decoration: const InputDecoration(
                  labelText: 'Cédula *',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: telefonoController,
                decoration: const InputDecoration(
                  labelText: 'Teléfono',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: correoController,
                decoration: const InputDecoration(
                  labelText: 'Correo',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () async {
              if (nombreController.text.isEmpty ||
                  apellidoController.text.isEmpty ||
                  cedulaController.text.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Complete los campos obligatorios')),
                );
                return;
              }

              final nuevoCliente = Cliente(
                nombre: nombreController.text,
                apellido: apellidoController.text,
                cedula: cedulaController.text,
                telefono: telefonoController.text.isEmpty ? null : telefonoController.text,
                correo: correoController.text.isEmpty ? null : correoController.text,
                isLocal: true,
              );

              final navigator = Navigator.of(context);
              final messenger = ScaffoldMessenger.of(context);

              // Se usa el cliente que devuelve createCliente: si hubo conexión,
              // tiene el ID del servidor (el original tiene un ID temporal).
              final guardado =
                  await context.read<ClientProvider>().createCliente(nuevoCliente);

              if (guardado != null) {
                navigator.pop(); // cierra el diálogo
                navigator.pop(guardado); // vuelve al POS con el cliente
              } else {
                messenger.showSnackBar(
                  const SnackBar(content: Text('Error al crear cliente')),
                );
              }
            },
            child: const Text('Guardar'),
          ),
        ],
      ),
    );
  }
}
