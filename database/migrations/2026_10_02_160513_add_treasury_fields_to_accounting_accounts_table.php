<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->string('type')->default('asset')->after('name');
            $table->string('sub_type')->default('cash')->after('type');
            $table->string('bank_name')->nullable()->after('sub_type');
            $table->string('card_number')->nullable()->after('account_number');
            $table->string('iban')->nullable()->after('card_number');

            $table->index(['business_id', 'sub_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'sub_type']);
            $table->dropColumn(['type', 'sub_type', 'bank_name', 'card_number', 'iban']);
        });
    }
};
