<?php

namespace App\Console\Commands\Updates;

/**
 * Blu Bank Excel statement import via AI: detect sheet "transaction",
 * parse Rial credit/debit columns, extract IBAN hint, and route Excel
 * uploads to import_bank_statement instead of invoice import.
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_20 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.20';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
