<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();

            $table->foreignId('gl_account_id')
                ->nullable()
                ->after('category_id')
                ->constrained('gl_accounts')
                ->restrictOnDelete();

            $table->foreignId('detail_level_4_id')
                ->nullable()
                ->after('project_id')
                ->constrained('gl_details')
                ->nullOnDelete();

            $table->foreignId('detail_level_5_id')
                ->nullable()
                ->after('detail_level_4_id')
                ->constrained('gl_details')
                ->nullOnDelete();

            $table->foreignId('detail_level_6_id')
                ->nullable()
                ->after('detail_level_5_id')
                ->constrained('gl_details')
                ->nullOnDelete();

            $table->index(['gl_account_id', 'created_at']);
            $table->index(['detail_level_4_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detail_level_6_id');
            $table->dropConstrainedForeignId('detail_level_5_id');
            $table->dropConstrainedForeignId('detail_level_4_id');
            $table->dropConstrainedForeignId('gl_account_id');
            $table->dropForeign(['category_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();
        });
    }
};
