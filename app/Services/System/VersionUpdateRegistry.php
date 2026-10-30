<?php

namespace App\Services\System;

use App\Console\Commands\Updates\Update_0_1_0_beta_1;
use App\Console\Commands\Updates\Update_0_1_0_beta_2;
use App\Console\Commands\Updates\Update_0_1_0_beta_3;
use App\Console\Commands\Updates\Update_0_1_0_beta_4;
use App\Console\Commands\Updates\Update_0_1_0_beta_5;
use App\Console\Commands\Updates\Update_0_1_0_beta_6;
use App\Console\Commands\Updates\Update_0_1_0_beta_7;
use App\Console\Commands\Updates\Update_0_1_0_beta_8;
use App\Console\Commands\Updates\Update_0_1_0_beta_9;
use App\Console\Commands\Updates\Update_0_1_0_beta_10;
use App\Console\Commands\Updates\Update_0_1_0_beta_11;
use App\Console\Commands\Updates\Update_0_1_0_beta_12;
use App\Console\Commands\Updates\Update_0_1_0_beta_13;
use App\Console\Commands\Updates\VersionUpdate;
use App\Support\System\AppVersion;

class VersionUpdateRegistry
{
    /**
     * Register every shipped release here, oldest → newest.
     *
     * @return list<class-string<VersionUpdate>>
     */
    public static function releaseClasses(): array
    {
        return [
            Update_0_1_0_beta_1::class,
            Update_0_1_0_beta_2::class,
            Update_0_1_0_beta_3::class,
            Update_0_1_0_beta_4::class,
            Update_0_1_0_beta_5::class,
            Update_0_1_0_beta_6::class,
            Update_0_1_0_beta_7::class,
            Update_0_1_0_beta_8::class,
            Update_0_1_0_beta_9::class,
            Update_0_1_0_beta_10::class,
            Update_0_1_0_beta_11::class,
            Update_0_1_0_beta_12::class,
            Update_0_1_0_beta_13::class,
        ];
    }

    /**
     * @return list<VersionUpdate>
     */
    public function all(): array
    {
        return array_map(
            fn (string $class): VersionUpdate => app($class),
            self::releaseClasses(),
        );
    }

    /**
     * Releases strictly newer than the installed version, sorted ascending.
     *
     * @return list<VersionUpdate>
     */
    public function pending(?string $installedVersion = null): array
    {
        $installed = $installedVersion ?? AppVersion::installed();

        $pending = array_values(array_filter(
            $this->all(),
            fn (VersionUpdate $update): bool => AppVersion::isPending($update::version(), $installed),
        ));

        usort(
            $pending,
            fn (VersionUpdate $a, VersionUpdate $b): int => version_compare($a::version(), $b::version()),
        );

        return $pending;
    }
}
