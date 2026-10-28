<?php

namespace App\Console\Commands\Updates;

/**
 * Ledger settings maintenance: backfill missing vouchers, convert manual
 * cash↔party settlement journals into treasury transactions, sync linked
 * vouchers, and cascade-delete transaction-linked journals with their source.
 */
class Update_0_1_0_beta_8 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.8';
    }

    public function artisanCommands(): array
    {
        return [
            ['accounting:convert-manual-settlements', '--all'],
        ];
    }
}
