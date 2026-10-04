<?php

namespace App\Jobs\System;

use App\Enums\System\UpdateMode;
use App\Services\System\ProjectUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateProjectJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(
        public UpdateMode $mode = UpdateMode::Full,
    ) {}

    public function handle(ProjectUpdater $updater): void
    {
        Log::info('Project update job started.', [
            'mode' => $this->mode->value,
        ]);

        $exitCode = $updater->run($this->mode, function (string $chunk): void {
            Log::info(rtrim($chunk));
        });

        if ($exitCode === 0) {
            Log::info('Project update job finished successfully.', [
                'mode' => $this->mode->value,
            ]);

            return;
        }

        Log::error('Project update job finished with errors.', [
            'mode' => $this->mode->value,
            'exit_code' => $exitCode,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Project update job failed.', [
            'mode' => $this->mode->value,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
