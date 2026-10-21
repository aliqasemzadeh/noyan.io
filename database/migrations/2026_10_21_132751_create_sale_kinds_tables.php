<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_kinds', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64);
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'code']);
            $table->index(['is_system', 'is_active', 'sort_order']);
        });

        Schema::create('business_sale_kinds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_kind_id')->constrained('sale_kinds')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'sale_kind_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_sale_kinds');
        Schema::dropIfExists('sale_kinds');
    }
};
