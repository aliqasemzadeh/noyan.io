<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedInteger('sequence_number')->nullable()->after('voucher_number');
            $table->string('sequence_scope', 40)->nullable()->after('sequence_number');

            $table->index(
                ['business_id', 'sequence_scope', 'sequence_number'],
                'journal_entries_sequence_index',
            );
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("
                UPDATE journal_entries
                SET sequence_number = CAST(voucher_number AS INTEGER),
                    sequence_scope = 'fy:' || fiscal_year_id
                WHERE voucher_number GLOB '[0-9]*'
                  AND voucher_number NOT LIKE '%__del_%'
            ");
        } else {
            DB::table('journal_entries')
                ->where('voucher_number', 'not like', '%__del_%')
                ->orderBy('id')
                ->chunkById(200, function ($entries): void {
                    foreach ($entries as $entry) {
                        if (! is_string($entry->voucher_number) || ! ctype_digit($entry->voucher_number)) {
                            continue;
                        }

                        DB::table('journal_entries')
                            ->where('id', $entry->id)
                            ->update([
                                'sequence_number' => (int) $entry->voucher_number,
                                'sequence_scope' => 'fy:'.$entry->fiscal_year_id,
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('journal_entries_sequence_index');
            $table->dropColumn(['sequence_number', 'sequence_scope']);
        });
    }
};
