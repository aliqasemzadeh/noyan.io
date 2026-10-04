<?php

namespace Tests\Unit;

use App\Services\System\SystemBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SystemBackupServiceTest extends TestCase
{
    public function test_list_local_backups_returns_sorted_zip_files(): void
    {
        Storage::fake('local');
        config(['backup.backup.name' => 'noyan-test']);

        Storage::disk('local')->put('noyan-test/older.zip', 'a');
        Storage::disk('local')->put('noyan-test/newer.zip', 'bb');
        Storage::disk('local')->put('noyan-test/notes.txt', 'ignore');

        $backups = app(SystemBackupService::class)->listLocalBackups();

        $this->assertCount(2, $backups);
        $this->assertTrue($backups->every(fn (array $backup) => str_ends_with($backup['name'], '.zip')));
    }

    public function test_run_database_backup_passes_only_db_and_local_disk(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', Mockery::on(function (array $options): bool {
                return ($options['--only-db'] ?? false) === true
                    && ($options['--only-to-disk'] ?? null) === 'local'
                    && ($options['--disable-notifications'] ?? false) === true;
            }))
            ->andReturn(0);

        $this->assertSame(0, app(SystemBackupService::class)->runDatabaseBackup());
    }

    public function test_delete_rejects_paths_outside_backup_prefix(): void
    {
        Storage::fake('local');
        config(['backup.backup.name' => 'noyan-test']);

        $this->expectException(RuntimeException::class);

        app(SystemBackupService::class)->delete('../secret.zip');
    }
}
