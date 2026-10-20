import '../models/detalle_orden.dart';

/// Resultado de la comparación de estados de una comanda
class ComandaChange {
  final bool tieneCambios;
  final List<DetalleCambio> detallesModificados;
  final List<DetalleOrden> detallesEliminados;
  final List<DetalleOrden> detallesAgregados;
  final String numeroComanda;

  ComandaChange({
    required this.tieneCambios,
    required this.detallesModificados,
    required this.detallesEliminados,
    required this.detallesAgregados,
    required this.numeroComanda,
  });

  bool get tieneModificaciones => detallesModificados.isNotEmpty;
  bool get tieneEliminaciones => detallesEliminados.isNotEmpty;
  bool get tieneAgregaciones => detallesAgregados.isNotEmpty;
}

/// Representa un cambio específico en un detalle
class DetalleCambio {
  final String productoId;
  final String productoNombre;
  final double cantidadAnterior;
  final double cantidadNueva;
  final double diferencia;

  DetalleCambio({
    required this.productoId,
    required this.productoNombre,
    required this.cantidadAnterior,
    required this.cantidadNueva,
    required this.diferencia,
  });
}

/// Servicio dedicado a la detección y gestión de cambios en comandas
class ComandaChangeService {
  /// Compara el estado anterior de una comanda con el estado actual
  /// para detectar cambios, modificaciones, eliminaciones y agregaciones
  ComandaChange detectarCambios(
    List<DetalleOrden> estadoAnterior,
    List<DetalleOrden> estadoActual,
    String numeroComanda,
  ) {
    final detallesModificados = <DetalleCambio>[];
    final detallesEliminados = <DetalleOrden>[];
    final detallesAgregados = <DetalleOrden>[];

    // Crear mapa para búsqueda eficiente por productoId
    final mapaAnterior = <String, DetalleOrden>{};
    for (var detalle in estadoAnterior) {
      mapaAnterior[detalle.productoId] = detalle;
    }

    final mapaActual = <String, DetalleOrden>{};
    for (var detalle in estadoActual) {
      mapaActual[detalle.productoId] = detalle;
    }

    // Detectar modificaciones y agregaciones
    for (var detalleActual in estadoActual) {
      final detalleAnterior = mapaAnterior[detalleActual.productoId];
      
      if (detalleAnterior == null) {
        // Producto nuevo (agregado)
        detallesAgregados.add(detalleActual);
      } else if (detalleActual.cantidad != detalleAnterior.cantidad) {
        // Cantidad modificada
        detallesModificados.add(DetalleCambio(
          productoId: detalleActual.productoId,
          productoNombre: detalleActual.productoNombre,
          cantidadAnterior: detalleAnterior.cantidad,
          cantidadNueva: detalleActual.cantidad,
          diferencia: detalleActual.cantidad - detalleAnterior.cantidad,
        ));
      }
    }

    // Detectar eliminaciones
    for (var detalleAnterior in estadoAnterior) {
      if (!mapaActual.containsKey(detalleAnterior.productoId)) {
        detallesEliminados.add(detalleAnterior);
      }
    }

    return ComandaChange(
      tieneCambios: detallesModificados.isNotEmpty ||
                    detallesEliminados.isNotEmpty ||
                    detallesAgregados.isNotEmpty,
      detallesModificados: detallesModificados,
      detallesEliminados: detallesEliminados,
      detallesAgregados: detallesAgregados,
      numeroComanda: numeroComanda,
    );
  }
}
