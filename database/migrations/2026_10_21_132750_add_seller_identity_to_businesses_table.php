<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('national_id', 20)->nullable()->after('address');
            $table->string('economic_code', 12)->nullable()->after('national_id');
            $table->string('postal_code', 16)->nullable()->after('economic_code');
            $table->string('tax_memory_id', 64)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_name',
                'national_id',
                'economic_code',
                'postal_code',
                'tax_memory_id',
            ]);
        });
    }
};
