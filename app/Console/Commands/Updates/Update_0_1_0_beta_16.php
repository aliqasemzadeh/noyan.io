<?php

namespace App\Console\Commands\Updates;

/**
 * Bank Mellat CSV statement import via AI, transaction Excel export,
 * Jalali date-picker Livewire sync fix, and fee sibling cascade on
 * transaction delete (meta.fee_of_transaction_id).
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_16 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.16';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
