<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
