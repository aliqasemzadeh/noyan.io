<?php

namespace App\Console\Commands\Updates;

/**
 * Bank Resalat CSV statement import via AI, auto-detect Mellat vs Resalat
 * formats, shared bank CSV parsing concerns, and fee-first chronological
 * ordering for same-timestamp batch rows.
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_17 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.17';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
