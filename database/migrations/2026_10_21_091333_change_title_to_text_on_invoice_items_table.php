<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->text('title')->change();
        });

        DB::table('invoice_items')
            ->orderBy('id')
            ->select(['id', 'title'])
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $title = (string) $row->title;

                    if ($title === '' || str_starts_with(ltrim($title), '<')) {
                        continue;
                    }

                    DB::table('invoice_items')
                        ->where('id', $row->id)
                        ->update([
                            'title' => '<p>'.e($title).'</p>',
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('invoice_items')
            ->orderBy('id')
            ->select(['id', 'title'])
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $plain = mb_substr(trim(strip_tags((string) $row->title)), 0, 255);

                    DB::table('invoice_items')
                        ->where('id', $row->id)
                        ->update([
                            'title' => $plain === '' ? '-' : $plain,
                        ]);
                }
            });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('title', 255)->change();
        });
    }
};
