<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->string('detailable_type');
            $table->unsignedBigInteger('detailable_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'detailable_type', 'detailable_id'], 'gl_details_business_detailable_unique');
            $table->index(['business_id', 'type', 'is_active']);
            $table->index(['detailable_type', 'detailable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_details');
    }
};
