<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_types', function (Blueprint $table): void {
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

        Schema::create('business_settlement_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_type_id')->constrained('settlement_types')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'settlement_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settlement_types');
        Schema::dropIfExists('settlement_types');
    }
};
