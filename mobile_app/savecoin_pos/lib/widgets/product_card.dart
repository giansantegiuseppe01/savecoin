import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../models/producto.dart';

class ProductCard extends StatelessWidget {
  final Producto producto;
  final VoidCallback onTap;

  const ProductCard({
    super.key,
    required this.producto,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Imagen del producto
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.grey[200],
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                ),
                child: producto.imagenUrl != null && producto.imagenUrl!.isNotEmpty
                    ? CachedNetworkImage(
                        imageUrl: producto.imagenUrl!,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => const Center(
                          child: CircularProgressIndicator(),
                        ),
                        errorWidget: (context, url, error) => const Icon(
                          Icons.restaurant,
                          size: 48,
                          color: Colors.grey,
                        ),
                      )
                    : const Icon(
                        Icons.restaurant,
                        size: 48,
                        color: Colors.grey,
                      ),
              ),
            ),
            
            // Información del producto
            Padding(
              padding: const EdgeInsets.all(8.0),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                    Text(
                      producto.nombre,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (producto.categoria != null)
                      Text(
                        producto.categoria!,
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey[600],
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    const SizedBox(height: 2),
                    Text(
                      '\$${producto.precioVenta.toStringAsFixed(2)}',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                        color: Colors.blue[700],
                      ),
                    ),
                  ],
                ),
            ),
          ],
        ),
      ),
    );
  }
}
