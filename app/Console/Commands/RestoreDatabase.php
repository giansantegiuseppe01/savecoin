<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Argument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use ZipArchive;

#[Signature('backup:restore {file}')]
#[Description('Restore database from backup file')]
class RestoreDatabase extends Command
{
    #[Argument(description: 'Backup file name (e.g., 2026-10-05_12-30-45.zip)')]
    public string $file;

    public function handle(): int
    {
        $backupDir = storage_path('backups');
        $backupZip = "{$backupDir}/{$this->file}";

        if (! file_exists($backupZip)) {
            $this->error("Backup no encontrado: {$backupZip}");

            return 1;
        }

        $this->warn('⚠️  ADVERTENCIA: Esto sobrescribirá la base de datos actual');
        $confirmed = $this->confirm('¿Está seguro de continuar?');

        if (! $confirmed) {
            $this->info('Restauración cancelada');

            return 0;
        }

        $this->info('Descomprimiendo backup...');

        // Descomprimir
        $zip = new ZipArchive;
        if ($zip->open($backupZip) !== true) {
            $this->error('Error al abrir archivo ZIP');

            return 1;
        }

        $tempDir = "{$backupDir}/temp";
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $sqlFile = "{$tempDir}/database.sql";

        if (! file_exists($sqlFile)) {
            $this->error('Archivo SQL no encontrado en el backup');
            $this->cleanTemp($tempDir);

            return 1;
        }

        $this->info('Restaurando base de datos...');

        // Restaurar
        $dbHost = config('database.connections.pgsql.host');
        $dbDatabase = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbPassword = config('database.connections.pgsql.password');

        $command = sprintf(
            'PGPASSWORD=%s psql -h %s -U %s -d %s -f %s',
            $dbPassword,
            $dbHost,
            $dbUser,
            $dbDatabase,
            $sqlFile
        );

        exec($command, $output, $returnCode);

        // Limpiar archivos temporales
        $this->cleanTemp($tempDir);

        if ($returnCode !== 0) {
            $this->error('Error al restaurar base de datos');
            $this->error('Código de retorno: '.$returnCode);

            return 1;
        }

        $this->info('Base de datos restaurada exitosamente');

        return 0;
    }

    private function cleanTemp(string $tempDir): void
    {
        if (file_exists($tempDir)) {
            array_map('unlink', glob("{$tempDir}/*"));
            rmdir($tempDir);
        }
    }
}
