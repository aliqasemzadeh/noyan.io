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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('type', 32);
            $table->string('title', 150);
            $table->decimal('principal_amount', 36, 18);
            $table->decimal('interest_amount', 36, 18)->default(0);
            $table->decimal('total_amount', 36, 18);
            $table->decimal('paid_amount', 36, 18)->default(0);
            $table->unsignedInteger('installments_count')->nullable();
            $table->decimal('installment_amount', 36, 18)->nullable();
            $table->date('issue_date');
            $table->date('first_installment_date')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'type', 'status']);
            $table->index(['business_id', 'party_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
