<?php

namespace App\Console\Commands\Updates;

/**
 * Blu Bank Excel import balance preflight: allow import when ledger equals
 * statement opening (even if opening > statement end), block only when truly
 * ahead, surface already-applied / opening-mismatch messages, keep prior
 * balance + original_filename hints. No one-shot artisan work.
 */
class Update_0_1_0_beta_21 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.21';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
