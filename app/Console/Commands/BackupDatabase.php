<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use ZipArchive;

#[Signature('backup:database')]
#[Description('Backup PostgreSQL database and compress it')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $config = config('database.connections.pgsql');

        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('backups');
        $backupSql = "{$backupDir}/{$timestamp}.sql";
        $backupZip = "{$backupDir}/{$timestamp}.zip";

        // Crear directorio si no existe (solo accesible por el propietario)
        if (! file_exists($backupDir)) {
            mkdir($backupDir, 0700, true);
        }

        $this->info('Iniciando backup de base de datos...');

        // pg_dump se ejecuta SIN shell: los argumentos se pasan como arreglo (no hay
        // inyección de comandos) y la contraseña va por variable de entorno, no en
        // la línea de comandos (donde cualquier usuario podría verla con `ps`).
        $process = new Process(
            [
                'pg_dump',
                '--host', (string) $config['host'],
                '--port', (string) ($config['port'] ?? 5432),
                '--username', (string) $config['username'],
                '--file', $backupSql,
                (string) $config['database'],
            ],
            null,
            ['PGPASSWORD' => (string) $config['password']],
        );
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Error al ejecutar pg_dump');
            $this->error('Código de retorno: '.$process->getExitCode());
            $this->error(trim($process->getErrorOutput()));

            if (file_exists($backupSql)) {
                unlink($backupSql);
            }

            return 1;
        }

        $this->info('Backup SQL creado exitosamente');

        // Comprimir backup
        $zip = new ZipArchive;
        if ($zip->open($backupZip, ZipArchive::CREATE) !== true) {
            $this->error('Error al crear archivo ZIP');
            unlink($backupSql);

            return 1;
        }

        $zip->addFile($backupSql, 'database.sql');
        $zip->close();

        // Eliminar SQL original
        unlink($backupSql);
        chmod($backupZip, 0600);

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
