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
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32)->default('individual');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('economic_code', 12)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->decimal('balance', 36, 18)->default(0);
            $table->decimal('credit_limit', 36, 18)->default(0);
            $table->boolean('is_customer')->default(true);
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'type']);
            $table->index(['business_id', 'is_customer']);
            $table->index(['business_id', 'is_supplier']);
            $table->index(['business_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
