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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('type', 32)->default('goods');
            $table->string('name');
            $table->string('slug');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('unit', 32)->default('piece');
            $table->text('description')->nullable();
            $table->decimal('purchase_price', 36, 18)->default(0);
            $table->decimal('sale_price', 36, 18)->default(0);
            $table->decimal('average_cost', 36, 18)->default(0);
            $table->decimal('last_purchase_price', 36, 18)->default(0);
            $table->boolean('track_inventory')->default(true);
            $table->decimal('stock_quantity', 36, 18)->default(0);
            $table->decimal('reserved_quantity', 36, 18)->default(0);
            $table->decimal('min_stock', 36, 18)->default(0);
            $table->decimal('max_stock', 36, 18)->nullable();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'barcode']);
            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'type']);
            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'category_id']);
            $table->index(['business_id', 'brand_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
