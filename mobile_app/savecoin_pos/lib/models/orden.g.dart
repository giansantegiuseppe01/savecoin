// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'orden.dart';

// **************************************************************************
// TypeAdapterGenerator
// **************************************************************************

class OrdenAdapter extends TypeAdapter<Orden> {
  @override
  final int typeId = 3;

  @override
  Orden read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return Orden(
      id: fields[0] as String?,
      numeroRecibo: fields[1] as String,
      clienteId: fields[2] as String,
      clienteNombre: fields[3] as String,
      tipo: fields[4] as String,
      subtotal: fields[5] as double,
      descuento: fields[6] as double,
      impuesto: fields[7] as double,
      total: fields[8] as double,
      tasaCambio: fields[10] as double,
      estado: fields[11] as String,
      metodoPago: fields[12] as String?,
      turno: fields[13] as String?,
      fechaHora: fields[14] as DateTime?,
      notas: fields[15] as String?,
      syncedAt: fields[16] as DateTime?,
      isLocal: fields[17] as bool,
      aplicaIva: fields[9] as bool,
      detalleIds: (fields[18] as List?)?.cast<String>(),
      referenciaPago: fields[19] as String?,
    );
  }

  @override
  void write(BinaryWriter writer, Orden obj) {
    writer
      ..writeByte(20)
      ..writeByte(0)
      ..write(obj.id)
      ..writeByte(1)
      ..write(obj.numeroRecibo)
      ..writeByte(2)
      ..write(obj.clienteId)
      ..writeByte(3)
      ..write(obj.clienteNombre)
      ..writeByte(4)
      ..write(obj.tipo)
      ..writeByte(5)
      ..write(obj.subtotal)
      ..writeByte(6)
      ..write(obj.descuento)
      ..writeByte(7)
      ..write(obj.impuesto)
      ..writeByte(8)
      ..write(obj.total)
      ..writeByte(9)
      ..write(obj.aplicaIva)
      ..writeByte(10)
      ..write(obj.tasaCambio)
      ..writeByte(11)
      ..write(obj.estado)
      ..writeByte(12)
      ..write(obj.metodoPago)
      ..writeByte(13)
      ..write(obj.turno)
      ..writeByte(14)
      ..write(obj.fechaHora)
      ..writeByte(15)
      ..write(obj.notas)
      ..writeByte(16)
      ..write(obj.syncedAt)
      ..writeByte(17)
      ..write(obj.isLocal)
      ..writeByte(18)
      ..write(obj.detalleIds)
      ..writeByte(19)
      ..write(obj.referenciaPago);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is OrdenAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}
