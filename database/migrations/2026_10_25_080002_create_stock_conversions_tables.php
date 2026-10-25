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
        Schema::create('stock_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->string('conversion_number');
            $table->string('status', 32)->default('draft');
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'conversion_number']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'occurred_at']);
            $table->index(['warehouse_id']);
            $table->index(['project_id']);
            $table->index(['cost_center_id']);
        });

        Schema::create('stock_conversion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_conversion_id')->constrained('stock_conversions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('direction', 16);
            $table->decimal('quantity', 36, 18);
            $table->timestamps();

            $table->index(['stock_conversion_id', 'direction']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_conversion_items');
        Schema::dropIfExists('stock_conversions');
    }
};
