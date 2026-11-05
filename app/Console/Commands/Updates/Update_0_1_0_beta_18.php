<?php

namespace App\Console\Commands\Updates;

/**
 * Bulk convert selected treasury transactions into journal vouchers
 * (transactions index + account ledger), merge matching expense/income
 * pairs into an internal transfer + single JE, and show bidirectional
 * links between transactions and journal entries.
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_18 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.18';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
