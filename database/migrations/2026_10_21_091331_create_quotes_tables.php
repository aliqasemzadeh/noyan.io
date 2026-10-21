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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('party_name', 150)->nullable();
            $table->string('quote_number', 60);
            $table->date('issue_date');
            $table->date('valid_until');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->decimal('items_total', 36, 18)->default(0);
            $table->decimal('global_discount', 36, 18)->default(0);
            $table->decimal('global_tax', 36, 18)->default(0);
            $table->decimal('total_amount', 36, 18)->default(0);
            $table->json('meta')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'quote_number'], 'unique_quote_number');
            $table->index(['business_id', 'issue_date']);
            $table->index(['business_id', 'finalized_at']);
            $table->index(['business_id', 'valid_until']);
            $table->index('invoice_id');
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->text('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('quantity', 36, 18)->default(1);
            $table->decimal('unit_price', 36, 18)->default(0);
            $table->decimal('discount_amount', 36, 18)->default(0);
            $table->decimal('tax_amount', 36, 18)->default(0);
            $table->decimal('total', 36, 18)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['quote_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
