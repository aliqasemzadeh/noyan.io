<?php

use App\Support\Accounting\SplitSaleKindsIntoSettlementTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('settlement_type_id')
                ->nullable()
                ->after('sale_kind_id')
                ->constrained('settlement_types')
                ->nullOnDelete();
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('settlement_type_id')
                ->nullable()
                ->after('sale_kind_id')
                ->constrained('settlement_types')
                ->nullOnDelete();
        });

        (new SplitSaleKindsIntoSettlementTypes)->handle();

        if (Schema::hasColumn('invoices', 'settlement_type')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('settlement_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'settlement_type')) {
                $table->string('settlement_type', 32)->nullable()->after('sale_kind_id');
            }
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settlement_type_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settlement_type_id');
        });
    }
};
