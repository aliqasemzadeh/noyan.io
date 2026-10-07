<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('category');
            $table->text('address')->nullable()->after('phone');
            $table->string('invoice_primary_color', 7)->nullable()->after('address');
            $table->string('invoice_secondary_color', 7)->nullable()->after('invoice_primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'address',
                'invoice_primary_color',
                'invoice_secondary_color',
            ]);
        });
    }
};
