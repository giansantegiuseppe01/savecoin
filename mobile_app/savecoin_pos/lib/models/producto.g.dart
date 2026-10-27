// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'producto.dart';

// **************************************************************************
// TypeAdapterGenerator
// **************************************************************************

class ProductoAdapter extends TypeAdapter<Producto> {
  @override
  final int typeId = 1;

  @override
  Producto read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return Producto(
      id: fields[0] as String?,
      nombre: fields[1] as String,
      codigo: fields[2] as String,
      descripcion: fields[3] as String?,
      precioVenta: fields[4] as double,
      categoria: fields[5] as String?,
      imagenUrl: fields[6] as String?,
      activo: fields[7] as bool,
      preparadoEnCocina: fields[8] as bool,
      syncedAt: fields[10] as DateTime?,
      isLocal: fields[11] as bool,
    );
  }

  @override
  void write(BinaryWriter writer, Producto obj) {
    writer
      ..writeByte(11)
      ..writeByte(0)
      ..write(obj.id)
      ..writeByte(1)
      ..write(obj.nombre)
      ..writeByte(2)
      ..write(obj.codigo)
      ..writeByte(3)
      ..write(obj.descripcion)
      ..writeByte(4)
      ..write(obj.precioVenta)
      ..writeByte(5)
      ..write(obj.categoria)
      ..writeByte(6)
      ..write(obj.imagenUrl)
      ..writeByte(7)
      ..write(obj.activo)
      ..writeByte(8)
      ..write(obj.preparadoEnCocina)
      ..writeByte(10)
      ..write(obj.syncedAt)
      ..writeByte(11)
      ..write(obj.isLocal);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is ProductoAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}
