<?php

use App\Enums\CategoryType;
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
        if (Schema::hasTable('product_categories')) {
            DB::table('product_categories')->orderBy('id')->chunkById(200, function ($rows): void {
                $payload = collect($rows)->map(fn ($row): array => [
                    'id' => $row->id,
                    'business_id' => $row->business_id,
                    'parent_id' => $row->parent_id,
                    'type' => CategoryType::Product->value,
                    'code' => null,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'description' => $row->description,
                    'is_system' => false,
                    'sort_order' => $row->sort_order,
                    'is_active' => $row->is_active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ])->all();

                if ($payload !== []) {
                    DB::table('categories')->insert($payload);
                }
            });

            $this->bumpCategoriesAutoIncrement();
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropForeign(['category_id']);
            });

            Schema::table('products', function (Blueprint $table): void {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->nullOnDelete();
            });
        }

        Schema::dropIfExists('product_categories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'parent_id']);
            $table->index(['business_id', 'is_active']);
        });

        DB::table('categories')
            ->where('type', CategoryType::Product->value)
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $payload = collect($rows)->map(fn ($row): array => [
                    'id' => $row->id,
                    'business_id' => $row->business_id,
                    'parent_id' => $row->parent_id,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'description' => $row->description,
                    'sort_order' => $row->sort_order,
                    'is_active' => $row->is_active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ])->all();

                if ($payload !== []) {
                    DB::table('product_categories')->insert($payload);
                }
            });

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropForeign(['category_id']);
            });

            Schema::table('products', function (Blueprint $table): void {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('product_categories')
                    ->nullOnDelete();
            });
        }

        DB::table('categories')->where('type', CategoryType::Product->value)->delete();
    }

    protected function bumpCategoriesAutoIncrement(): void
    {
        $maxId = (int) DB::table('categories')->max('id');

        if ($maxId < 1) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $nextId = $maxId + 1;

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE categories AUTO_INCREMENT = {$nextId}");
        }

        if ($driver === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('categories', 'id'), {$maxId})");
        }
    }
};
