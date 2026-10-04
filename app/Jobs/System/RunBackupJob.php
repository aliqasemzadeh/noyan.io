<?php

namespace App\Jobs\System;

use App\Services\System\SystemBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunBackupJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    /**
     * Backups are database-only and local for this product phase.
     */
    public function __construct(
        public string $type = 'database',
        public string $destination = 'local',
    ) {}

    public function handle(SystemBackupService $backups): void
    {
        Log::info('Backup job started.', [
            'type' => 'database',
            'destination' => 'local',
        ]);

        $exitCode = $backups->runDatabaseBackup();
        $output = $backups->output();

        if ($exitCode === 0) {
            Log::info('Backup job finished successfully.', [
                'type' => 'database',
                'destination' => 'local',
                'output' => $output,
            ]);

            return;
        }

        Log::error('Backup job finished with errors.', [
            'type' => 'database',
            'destination' => 'local',
            'exit_code' => $exitCode,
            'output' => $output,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Backup job failed.', [
            'type' => 'database',
            'destination' => 'local',
            'exception' => $exception?->getMessage(),
        ]);
    }
}
