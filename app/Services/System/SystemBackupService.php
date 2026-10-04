<?php

namespace App\Services\System;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SystemBackupService
{
    /**
     * @return Collection<int, array{path: string, name: string, size: int, last_modified: Carbon}>
     */
    public function listLocalBackups(): Collection
    {
        $disk = Storage::disk($this->disk());
        $prefix = trim($this->backupName(), '/');

        return collect($disk->allFiles($prefix))
            ->filter(fn (string $path): bool => Str::endsWith(strtolower($path), ['.zip', '.gz']))
            ->map(function (string $path) use ($disk): array {
                return [
                    'path' => $path,
                    'name' => basename($path),
                    'size' => (int) $disk->size($path),
                    'last_modified' => Carbon::createFromTimestamp($disk->lastModified($path)),
                ];
            })
            ->sortByDesc(fn (array $backup): int => $backup['last_modified']->getTimestamp())
            ->values();
    }

    public function runDatabaseBackup(): int
    {
        return Artisan::call('backup:run', [
            '--only-db' => true,
            '--only-to-disk' => $this->disk(),
            '--disable-notifications' => true,
        ]);
    }

    public function output(): string
    {
        return trim(Artisan::output());
    }

    public function delete(string $path): void
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $prefix = trim($this->backupName(), '/').'/';

        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, $prefix)) {
            throw new RuntimeException(__('general.backup_delete_invalid'));
        }

        $disk = Storage::disk($this->disk());

        if (! $disk->exists($path)) {
            throw new RuntimeException(__('general.backup_not_found'));
        }

        $disk->delete($path);
    }

    public function disk(): string
    {
        return 'local';
    }

    public function backupName(): string
    {
        return (string) config('backup.backup.name', config('app.name', 'laravel-backup'));
    }

    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) max($bytes, 0);
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 2).' '.$units[$unit];
    }
}
