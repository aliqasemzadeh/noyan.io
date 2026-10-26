<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_ledger_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('group_code_length')->default(1)->after('auto_post_mode');
            $table->unsignedTinyInteger('control_code_length')->default(1)->after('group_code_length');
            $table->unsignedTinyInteger('subsidiary_code_length')->default(2)->after('control_code_length');
        });
    }

    public function down(): void
    {
        Schema::table('business_ledger_settings', function (Blueprint $table) {
            $table->dropColumn([
                'group_code_length',
                'control_code_length',
                'subsidiary_code_length',
            ]);
        });
    }
};
