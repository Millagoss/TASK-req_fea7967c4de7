<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:run';
    protected $description = 'Run nightly MySQL backup';

    public function handle(): int
    {
        $backupDir = '/backups';
        $timestamp = now()->format('Ymd_His');
        $filename = "meridian_backup_{$timestamp}.sql.gz";
        $filepath = "{$backupDir}/{$filename}";

        $host = config('database.connections.mysql.host');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $this->info('Starting database backup...');

        $command = sprintf(
            'mysqldump -h %s -u %s -p%s --single-transaction --routines --triggers %s | gzip > %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        $result = null;
        $output = null;
        exec($command . ' 2>&1', $output, $result);

        if ($result !== 0) {
            $this->error('Backup failed: ' . implode("\n", $output));
            return self::FAILURE;
        }

        $this->info("Backup saved: {$filepath}");

        // Clean up backups older than 30 days
        $cleanCommand = sprintf(
            'find %s -name "meridian_backup_*.sql.gz" -mtime +30 -delete',
            escapeshellarg($backupDir)
        );
        exec($cleanCommand);
        $this->info('Old backups cleaned (30-day retention).');

        return self::SUCCESS;
    }
}
