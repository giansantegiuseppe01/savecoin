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

  /// Marca detalles como modificados basándose en los cambios detectados
  List<DetalleOrden> aplicarMarcasDeModificacion(
    List<DetalleOrden> detallesActuales,
    ComandaChange cambios,
  ) {
    final detallesActualizados = <DetalleOrden>[];

    for (var detalle in detallesActuales) {
      // Verificar si este detalle fue modificado
      final fueModificado = cambios.detallesModificados.any(
        (c) => c.productoId == detalle.productoId
      ) || cambios.detallesAgregados.any(
        (d) => d.productoId == detalle.productoId
      );

      detallesActualizados.add(detalle.copyWith(
        fueModificado: fueModificado,
        isNew: false, // Resetear flag isNew después de modificación
      ));
    }

    return detallesActualizados;
  }

  /// Genera un resumen legible de los cambios para mostrar en UI o logs
  String generarResumenCambios(ComandaChange cambios) {
    if (!cambios.tieneCambios) {
      return 'Sin cambios detectados';
    }

    final buffer = StringBuffer();
    buffer.writeln('Cambios en Comanda #${cambios.numeroComanda}:');

    if (cambios.tieneModificaciones) {
      buffer.writeln('  Modificaciones:');
      for (var cambio in cambios.detallesModificados) {
        final signo = cambio.diferencia > 0 ? '+' : '';
        buffer.writeln('    - ${cambio.productoNombre}: '
                      '${cambio.cantidadAnterior} → ${cambio.cantidadNueva} '
                      '($signo${cambio.diferencia})');
      }
    }

    if (cambios.tieneEliminaciones) {
      buffer.writeln('  Eliminaciones:');
      for (var detalle in cambios.detallesEliminados) {
        buffer.writeln('    - ${detalle.productoNombre} (x${detalle.cantidad})');
      }
    }

    if (cambios.tieneAgregaciones) {
      buffer.writeln('  Agregaciones:');
      for (var detalle in cambios.detallesAgregados) {
        buffer.writeln('    - ${detalle.productoNombre} (x${detalle.cantidad})');
      }
    }

    return buffer.toString();
  }
}