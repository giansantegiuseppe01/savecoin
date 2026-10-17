// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'detalle_orden.dart';

// **************************************************************************
// TypeAdapterGenerator
// **************************************************************************

class DetalleOrdenAdapter extends TypeAdapter<DetalleOrden> {
  @override
  final int typeId = 4;

  @override
  DetalleOrden read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return DetalleOrden(
      id: fields[0] as String?,
      ordenId: fields[1] as String,
      productoId: fields[2] as String,
      productoNombre: fields[3] as String,
      precioUnitario: fields[4] as double,
      cantidad: fields[5] as double,
      notas: fields[7] as String?,
      syncedAt: fields[8] as DateTime?,
      isLocal: fields[9] as bool,
      isNew: fields[10] as bool,
      fueModificado: fields[11] as bool,
    )..subtotal = fields[6] as double;
  }

  @override
  void write(BinaryWriter writer, DetalleOrden obj) {
    writer
      ..writeByte(12)
      ..writeByte(0)
      ..write(obj.id)
      ..writeByte(1)
      ..write(obj.ordenId)
      ..writeByte(2)
      ..write(obj.productoId)
      ..writeByte(3)
      ..write(obj.productoNombre)
      ..writeByte(4)
      ..write(obj.precioUnitario)
      ..writeByte(5)
      ..write(obj.cantidad)
      ..writeByte(6)
      ..write(obj.subtotal)
      ..writeByte(7)
      ..write(obj.notas)
      ..writeByte(8)
      ..write(obj.syncedAt)
      ..writeByte(9)
      ..write(obj.isLocal)
      ..writeByte(10)
      ..write(obj.isNew)
      ..writeByte(11)
      ..write(obj.fueModificado);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is DetalleOrdenAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}
