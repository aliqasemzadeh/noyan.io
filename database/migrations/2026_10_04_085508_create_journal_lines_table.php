<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->decimal('debit', 22, 4)->default(0);
            $table->decimal('credit', 22, 4)->default(0);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['category_id', 'created_at']);
            $table->index(['party_id', 'created_at']);
            $table->index(['account_id', 'created_at']);
            $table->index(['cost_center_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
