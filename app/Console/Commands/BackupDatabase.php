<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Automatic database backups (added after the 2026-07-26 data-loss incident).
 * Dumps the whole database to storage/app/backups as gzipped SQL and prunes
 * old files. Scheduled every 6 hours in routes/console.php; run manually with
 * `php artisan db:backup`.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=40 : How many backup files to retain}';

    protected $description = 'Dump the database to storage/app/backups (gzipped), pruning old backups';

    public function handle(): int
    {
        $cfg = config('database.connections.'.config('database.default'));
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = sprintf('%s/%s-%s.sql.gz', $dir, $cfg['database'], now()->format('Ymd-His'));

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --routines --triggers %s | gzip > %s',
            escapeshellarg($cfg['host']),
            escapeshellarg((string) $cfg['port']),
            escapeshellarg($cfg['username']),
            escapeshellarg($cfg['database']),
            escapeshellarg($file),
        );

        // Password via env var — never on the command line (visible in ps).
        $process = Process::fromShellCommandline($cmd, null, ['MYSQL_PWD' => $cfg['password']], null, 600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) < 1024) {
            @unlink($file);
            $this->error('Backup FAILED: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info('Backup written: '.basename($file).' ('.round(filesize($file) / 1024).' KB)');

        // Prune: keep the newest N files.
        $files = glob($dir.'/*.sql.gz');
        rsort($files); // newest first (timestamped names sort lexicographically)
        foreach (array_slice($files, max(1, (int) $this->option('keep'))) as $old) {
            unlink($old);
            $this->line('pruned '.basename($old));
        }

        return self::SUCCESS;
    }
}
