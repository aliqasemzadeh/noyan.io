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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('cheque_id')
                ->nullable()
                ->after('loan_id')
                ->constrained('cheques')
                ->nullOnDelete();

            $table->index(['business_id', 'cheque_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'cheque_id']);
            $table->dropConstrainedForeignId('cheque_id');
        });
    }
};
