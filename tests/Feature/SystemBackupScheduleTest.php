<?php

namespace Tests\Feature;

use App\Jobs\System\RunBackupJob;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SystemBackupScheduleTest extends TestCase
{
    public function test_database_backup_is_scheduled_daily_at_six_tehran(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())->first(
            fn ($event) => str_contains($event->description ?? '', 'system-database-backup')
                || str_contains($event->getSummaryForDisplay(), 'RunBackupJob')
                || str_contains($event->command ?? '', 'RunBackupJob'),
        );

        $this->assertNotNull($event, 'Expected system-database-backup schedule event.');
        $this->assertSame('0 6 * * *', $event->expression);
        $this->assertSame('Asia/Tehran', $event->timezone);
    }

    public function test_scheduled_job_defaults_to_database_local(): void
    {
        $job = new RunBackupJob;

        $this->assertSame('database', $job->type);
        $this->assertSame('local', $job->destination);
    }
}
