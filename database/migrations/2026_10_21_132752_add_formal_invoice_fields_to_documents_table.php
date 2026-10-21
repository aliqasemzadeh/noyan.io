<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('sale_kind_id')->nullable()->after('type')->constrained('sale_kinds')->nullOnDelete();
            $table->string('settlement_type', 32)->nullable()->after('sale_kind_id');
            $table->string('buyer_legal_name')->nullable()->after('party_name');
            $table->string('buyer_national_id', 20)->nullable()->after('buyer_legal_name');
            $table->string('buyer_economic_code', 12)->nullable()->after('buyer_national_id');
            $table->string('buyer_postal_code', 16)->nullable()->after('buyer_economic_code');
            $table->string('buyer_phone', 50)->nullable()->after('buyer_postal_code');
            $table->text('buyer_address')->nullable()->after('buyer_phone');
            $table->string('moodian_tax_id', 32)->nullable()->after('note');
            $table->string('moodian_status', 32)->nullable()->after('moodian_tax_id');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('sale_kind_id')->nullable()->after('quote_number')->constrained('sale_kinds')->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->string('sku', 100)->nullable()->after('title');
            $table->string('unit', 32)->nullable()->after('sku');
        });

        Schema::table('quote_items', function (Blueprint $table): void {
            $table->string('sku', 100)->nullable()->after('title');
            $table->string('unit', 32)->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['sku', 'unit']);
        });

        Schema::table('quote_items', function (Blueprint $table): void {
            $table->dropColumn(['sku', 'unit']);
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sale_kind_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sale_kind_id');
            $table->dropColumn([
                'settlement_type',
                'buyer_legal_name',
                'buyer_national_id',
                'buyer_economic_code',
                'buyer_postal_code',
                'buyer_phone',
                'buyer_address',
                'moodian_tax_id',
                'moodian_status',
            ]);
        });
    }
};
