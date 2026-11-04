<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('balance_after', 36, 18)->nullable()->after('base_amount');
            $table->decimal('destination_balance_after', 36, 18)->nullable()->after('balance_after');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['balance_after', 'destination_balance_after']);
        });
    }
};
