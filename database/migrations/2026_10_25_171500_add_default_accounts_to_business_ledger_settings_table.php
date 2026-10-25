<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_ledger_settings', function (Blueprint $table) {
            $table->foreignId('default_accounts_receivable_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_accounts_payable_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_sales_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_sales_discount_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_vat_payable_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_vat_receivable_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_inventory_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_cogs_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_cash_and_bank_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_fx_gain_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignId('default_fx_loss_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_ledger_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_accounts_receivable_id');
            $table->dropConstrainedForeignId('default_accounts_payable_id');
            $table->dropConstrainedForeignId('default_sales_id');
            $table->dropConstrainedForeignId('default_sales_discount_id');
            $table->dropConstrainedForeignId('default_vat_payable_id');
            $table->dropConstrainedForeignId('default_vat_receivable_id');
            $table->dropConstrainedForeignId('default_inventory_id');
            $table->dropConstrainedForeignId('default_cogs_id');
            $table->dropConstrainedForeignId('default_cash_and_bank_id');
            $table->dropConstrainedForeignId('default_fx_gain_id');
            $table->dropConstrainedForeignId('default_fx_loss_id');
        });
    }
};
