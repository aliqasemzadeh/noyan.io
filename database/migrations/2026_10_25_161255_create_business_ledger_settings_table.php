<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_ledger_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('voucher_numbering_mode', 20)->default('yearly');
            $table->string('voucher_prefix', 20)->nullable();
            $table->unsignedTinyInteger('voucher_number_pad')->default(4);
            $table->string('default_voucher_status', 20)->default('draft');
            $table->string('auto_post_mode', 20)->default('off');
            $table->timestamps();

            $table->unique('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_ledger_settings');
    }
};
