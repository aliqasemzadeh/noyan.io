<?php

use App\Enums\Catalog\WarehouseType;
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
        Schema::table('product_stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('product_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('project_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('projects')
                ->nullOnDelete();
            $table->foreignId('cost_center_id')
                ->nullable()
                ->after('project_id')
                ->constrained('cost_centers')
                ->nullOnDelete();

            $table->index(['warehouse_id', 'occurred_at']);
            $table->index(['business_id', 'project_id']);
            $table->index(['business_id', 'cost_center_id']);
        });

        $this->backfillWarehousesAndStocks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }

    protected function backfillWarehousesAndStocks(): void
    {
        $now = now();

        $businessIds = DB::table('businesses')->pluck('id');

        foreach ($businessIds as $businessId) {
            $businessId = (int) $businessId;

            $existingDefaultId = DB::table('warehouses')
                ->where('business_id', $businessId)
                ->where('is_default', true)
                ->value('id');

            if ($existingDefaultId === null) {
                $presets = [
                    [
                        'type' => WarehouseType::Main->value,
                        'code' => WarehouseType::Main->defaultCode(),
                        'name' => 'انبار اصلی',
                        'is_default' => true,
                        'is_sellable' => true,
                    ],
                    [
                        'type' => WarehouseType::Consumable->value,
                        'code' => WarehouseType::Consumable->defaultCode(),
                        'name' => 'انبار مصرفی',
                        'is_default' => false,
                        'is_sellable' => false,
                    ],
                    [
                        'type' => WarehouseType::Project->value,
                        'code' => WarehouseType::Project->defaultCode(),
                        'name' => 'انبار پروژه',
                        'is_default' => false,
                        'is_sellable' => false,
                    ],
                    [
                        'type' => WarehouseType::Production->value,
                        'code' => WarehouseType::Production->defaultCode(),
                        'name' => 'انبار تولید',
                        'is_default' => false,
                        'is_sellable' => false,
                    ],
                ];

                foreach ($presets as $preset) {
                    $exists = DB::table('warehouses')
                        ->where('business_id', $businessId)
                        ->where('code', $preset['code'])
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('warehouses')->insert([
                        'business_id' => $businessId,
                        'name' => $preset['name'],
                        'code' => $preset['code'],
                        'type' => $preset['type'],
                        'is_default' => $preset['is_default'],
                        'is_sellable' => $preset['is_sellable'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $existingDefaultId = DB::table('warehouses')
                    ->where('business_id', $businessId)
                    ->where('is_default', true)
                    ->value('id');
            }

            if ($existingDefaultId === null) {
                continue;
            }

            $defaultWarehouseId = (int) $existingDefaultId;

            $products = DB::table('products')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->get(['id', 'stock_quantity', 'reserved_quantity', 'track_inventory']);

            foreach ($products as $product) {
                if (! $product->track_inventory) {
                    continue;
                }

                $stockExists = DB::table('inventory_stocks')
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $defaultWarehouseId)
                    ->exists();

                if ($stockExists) {
                    continue;
                }

                DB::table('inventory_stocks')->insert([
                    'business_id' => $businessId,
                    'product_id' => $product->id,
                    'warehouse_id' => $defaultWarehouseId,
                    'physical_quantity' => $product->stock_quantity ?? 0,
                    'reserved_quantity' => $product->reserved_quantity ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('product_stock_movements')
                ->where('business_id', $businessId)
                ->whereNull('warehouse_id')
                ->update(['warehouse_id' => $defaultWarehouseId]);
        }
    }
};
