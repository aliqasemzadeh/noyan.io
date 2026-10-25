<?php

namespace App\Console\Commands\Updates;

abstract class VersionUpdate
{
    abstract public static function version(): string;

    /**
     * Artisan argument lists to run for this release (after migrate).
     *
     * @return list<list<string>>
     */
    abstract public function artisanCommands(): array;

    public function description(): string
    {
        return 'Apply release '.static::version();
    }
}
