<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Argument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
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

        // basename() evita path traversal (p. ej. "../../etc/algo.zip"): solo se
        // aceptan archivos que estén directamente dentro de storage/backups.
        $backupZip = $backupDir.'/'.basename($this->file);

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

        $zip = new ZipArchive;
        if ($zip->open($backupZip) !== true) {
            $this->error('Error al abrir archivo ZIP');

            return 1;
        }

        // Se lee únicamente la entrada "database.sql" (sin extractTo), así un ZIP
        // manipulado no puede escribir archivos fuera del directorio (Zip Slip).
        $sql = $zip->getFromName('database.sql');
        $zip->close();

        if ($sql === false) {
            $this->error('Archivo SQL no encontrado en el backup');

            return 1;
        }

        $tempDir = "{$backupDir}/temp";
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0700, true);
        }

        $sqlFile = "{$tempDir}/database.sql";
        file_put_contents($sqlFile, $sql);
        chmod($sqlFile, 0600);

        $this->info('Restaurando base de datos...');

        $config = config('database.connections.pgsql');

        // Sin shell y con la contraseña por variable de entorno (ver BackupDatabase).
        $process = new Process(
            [
                'psql',
                '--host', (string) $config['host'],
                '--port', (string) ($config['port'] ?? 5432),
                '--username', (string) $config['username'],
                '--dbname', (string) $config['database'],
                '--file', $sqlFile,
            ],
            null,
            ['PGPASSWORD' => (string) $config['password']],
        );
        $process->setTimeout(1800);
        $process->run();

        // Limpiar archivos temporales
        $this->cleanTemp($tempDir);

        if (! $process->isSuccessful()) {
            $this->error('Error al restaurar base de datos');
            $this->error('Código de retorno: '.$process->getExitCode());
            $this->error(trim($process->getErrorOutput()));

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
