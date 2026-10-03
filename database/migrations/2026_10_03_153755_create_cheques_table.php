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
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('registered');
            $table->string('cheque_number', 50);
            $table->string('sayad_number', 16)->nullable();
            $table->string('bank_name', 100);
            $table->string('bank_branch', 100)->nullable();
            $table->decimal('amount', 36, 18);
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('cleared_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('party_reversed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'type', 'status']);
            $table->index(['business_id', 'due_date']);
            $table->index(['business_id', 'party_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
