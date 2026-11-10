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
      nombreEmpresa: fields[6] as String?,
      rif: fields[7] as String?,
      telefono: fields[8] as String?,
      direccion: fields[9] as String?,
      logoUrl: fields[10] as String?,
      logoBase64: fields[11] as String?,
      formasPagoJson: fields[12] as String?,
    );
  }

  @override
  void write(BinaryWriter writer, Configuracion obj) {
    writer
      ..writeByte(13)
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
      ..write(obj.lastSyncAt)
      ..writeByte(6)
      ..write(obj.nombreEmpresa)
      ..writeByte(7)
      ..write(obj.rif)
      ..writeByte(8)
      ..write(obj.telefono)
      ..writeByte(9)
      ..write(obj.direccion)
      ..writeByte(10)
      ..write(obj.logoUrl)
      ..writeByte(11)
      ..write(obj.logoBase64)
      ..writeByte(12)
      ..write(obj.formasPagoJson);
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
