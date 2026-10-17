// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'configuracion.dart';

// **************************************************************************
// TypeAdapterGenerator
// **************************************************************************

class ConfiguracionAdapter extends TypeAdapter<Configuracion> {
  @override
  final int typeId = 5;

  @override
  Configuracion read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return Configuracion(
      tasaCambio: fields[0] as double,
      printerMacAddress: fields[1] as String?,
      printerName: fields[2] as String?,
      apiUrl: fields[3] as String,
      apiToken: fields[4] as String,
      lastSyncAt: fields[5] as DateTime?,
    );
  }

  @override
  void write(BinaryWriter writer, Configuracion obj) {
    writer
      ..writeByte(6)
      ..writeByte(0)
      ..write(obj.tasaCambio)
      ..writeByte(1)
      ..write(obj.printerMacAddress)
      ..writeByte(2)
      ..write(obj.printerName)
      ..writeByte(3)
      ..write(obj.apiUrl)
      ..writeByte(4)
      ..write(obj.apiToken)
      ..writeByte(5)
      ..write(obj.lastSyncAt);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is ConfiguracionAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}
