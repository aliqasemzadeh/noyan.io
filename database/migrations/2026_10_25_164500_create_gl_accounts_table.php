<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('code', 30);
            $table->string('full_code', 60);
            $table->string('name');
            $table->string('nature', 20);
            $table->string('statement_type', 30);
            $table->boolean('is_postable')->default(false);
            $table->boolean('is_system')->default(false);
            $table->string('system_key', 60)->nullable();
            $table->string('requires_detail_level_4_type', 30)->nullable();
            $table->string('requires_detail_level_5_type', 30)->nullable();
            $table->string('requires_detail_level_6_type', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'full_code']);
            $table->unique(['business_id', 'system_key']);
            $table->index(['business_id', 'level', 'is_active']);
            $table->index(['business_id', 'parent_id']);
            $table->index(['business_id', 'is_postable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_accounts');
    }
};
