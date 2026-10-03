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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('party_name', 150)->nullable();
            $table->string('invoice_number', 60);
            $table->string('type', 32)->default('sale');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->decimal('items_total', 36, 18)->default(0);
            $table->decimal('global_discount', 36, 18)->default(0);
            $table->decimal('global_tax', 36, 18)->default(0);
            $table->decimal('total_amount', 36, 18)->default(0);
            $table->decimal('paid_amount', 36, 18)->default(0);
            $table->json('meta')->nullable();
            $table->string('payment_status', 32)->default('unpaid');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'type', 'invoice_number'], 'unique_invoice_number');
            $table->index(['business_id', 'issue_date']);
            $table->index(['business_id', 'finalized_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('title', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('quantity', 36, 18)->default(1);
            $table->decimal('unit_price', 36, 18)->default(0);
            $table->decimal('discount_amount', 36, 18)->default(0);
            $table->decimal('tax_amount', 36, 18)->default(0);
            $table->decimal('total', 36, 18)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['invoice_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
