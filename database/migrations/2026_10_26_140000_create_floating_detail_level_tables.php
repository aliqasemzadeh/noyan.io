<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_account_detail_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gl_account_id')->constrained('gl_accounts')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->string('detail_type', 30);
            $table->timestamps();

            $table->unique(['gl_account_id', 'level']);
        });

        Schema::create('journal_line_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_line_id')->constrained('journal_lines')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->foreignId('gl_detail_id')->constrained('gl_details')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['journal_line_id', 'level']);
            $table->index(['gl_detail_id', 'level']);
        });

        $now = now();

        foreach ([4, 5, 6] as $level) {
            $column = 'requires_detail_level_'.$level.'_type';

            DB::table('gl_accounts')
                ->whereNotNull($column)
                ->orderBy('id')
                ->select(['id', $column])
                ->get()
                ->each(function (object $row) use ($level, $column, $now): void {
                    DB::table('gl_account_detail_levels')->insert([
                        'gl_account_id' => $row->id,
                        'level' => $level,
                        'detail_type' => $row->{$column},
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }

        foreach ([4, 5, 6] as $level) {
            $column = 'detail_level_'.$level.'_id';

            DB::table('journal_lines')
                ->whereNotNull($column)
                ->orderBy('id')
                ->select(['id', $column])
                ->get()
                ->each(function (object $row) use ($level, $column, $now): void {
                    DB::table('journal_line_details')->insert([
                        'journal_line_id' => $row->id,
                        'level' => $level,
                        'gl_detail_id' => $row->{$column},
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['detail_level_6_id']);
            $table->dropForeign(['detail_level_5_id']);
            $table->dropForeign(['detail_level_4_id']);
        });

        foreach ([
            'journal_lines_detail_level_4_id_created_at_index',
            'journal_lines_detail_level_4_id_foreign',
            'journal_lines_detail_level_5_id_foreign',
            'journal_lines_detail_level_6_id_foreign',
        ] as $index) {
            if (collect(Schema::getIndexes('journal_lines'))->contains(fn (array $existing): bool => $existing['name'] === $index)) {
                Schema::table('journal_lines', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn([
                'detail_level_6_id',
                'detail_level_5_id',
                'detail_level_4_id',
            ]);
        });

        Schema::table('gl_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'requires_detail_level_4_type',
                'requires_detail_level_5_type',
                'requires_detail_level_6_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('gl_accounts', function (Blueprint $table) {
            $table->string('requires_detail_level_4_type', 30)->nullable();
            $table->string('requires_detail_level_5_type', 30)->nullable();
            $table->string('requires_detail_level_6_type', 30)->nullable();
        });

        Schema::table('journal_lines', function (Blueprint $table) {
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

            $table->index(['detail_level_4_id', 'created_at']);
        });

        foreach ([4, 5, 6] as $level) {
            DB::table('gl_account_detail_levels')
                ->where('level', $level)
                ->orderBy('id')
                ->get()
                ->each(function (object $row) use ($level): void {
                    DB::table('gl_accounts')
                        ->where('id', $row->gl_account_id)
                        ->update([
                            'requires_detail_level_'.$level.'_type' => $row->detail_type,
                        ]);
                });

            DB::table('journal_line_details')
                ->where('level', $level)
                ->orderBy('id')
                ->get()
                ->each(function (object $row) use ($level): void {
                    DB::table('journal_lines')
                        ->where('id', $row->journal_line_id)
                        ->update([
                            'detail_level_'.$level.'_id' => $row->gl_detail_id,
                        ]);
                });
        }

        Schema::dropIfExists('journal_line_details');
        Schema::dropIfExists('gl_account_detail_levels');
    }
};
