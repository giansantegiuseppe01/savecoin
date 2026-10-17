<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use ZipArchive;

#[Signature('backup:database')]
#[Description('Backup PostgreSQL database and compress it')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $dbHost = config('database.connections.pgsql.host');
        $dbDatabase = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbPassword = config('database.connections.pgsql.password');

        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('backups');
        $backupSql = "{$backupDir}/{$timestamp}.sql";
        $backupZip = "{$backupDir}/{$timestamp}.zip";

        // Crear directorio si no existe
        if (! file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $this->info('Iniciando backup de base de datos...');

        // Ejecutar pg_dump
        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -U %s %s > %s',
            $dbPassword,
            $dbHost,
            $dbUser,
            $dbDatabase,
            $backupSql
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('Error al ejecutar pg_dump');
            $this->error('Código de retorno: '.$returnCode);

            return 1;
        }

        $this->info('Backup SQL creado exitosamente');

        // Comprimir backup
        $zip = new ZipArchive;
        if ($zip->open($backupZip, ZipArchive::CREATE) !== true) {
            $this->error('Error al crear archivo ZIP');

            return 1;
        }

        $zip->addFile($backupSql, 'database.sql');
        $zip->close();

        // Eliminar SQL original
        unlink($backupSql);

        $this->info("Backup comprimido: {$backupZip}");

        // Limpiar backups antiguos (más de 30 días)
        $this->cleanOldBackups($backupDir);

        $this->info('Backup completado exitosamente');

        return 0;
    }

    private function cleanOldBackups(string $backupDir): void
    {
        $files = glob("{$backupDir}/*.zip");
        $thirtyDaysAgo = now()->subDays(30)->timestamp;

        foreach ($files as $file) {
            if (filemtime($file) < $thirtyDaysAgo) {
                unlink($file);
                $this->info('Backup antiguo eliminado: '.basename($file));
            }
        }
    }
}
