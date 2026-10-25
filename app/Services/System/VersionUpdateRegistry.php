<?php

namespace App\Services\System;

use App\Console\Commands\Updates\Update_0_1_0_beta_1;
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
