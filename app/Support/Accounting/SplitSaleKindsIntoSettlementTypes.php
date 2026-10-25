<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SplitSaleKindsIntoSettlementTypes
{
    /**
     * @var list<string>
     */
    public const SETTLEMENT_CODES = [
        'cash',
        'installment',
        'temporary_credit',
        'customary',
    ];

    public function handle(): void
    {
        $now = now();

        $legacySaleKinds = DB::table('sale_kinds')
            ->whereNull('deleted_at')
            ->where('is_system', true)
            ->whereNull('business_id')
            ->whereIn('code', self::SETTLEMENT_CODES)
            ->get()
            ->keyBy('code');

        if ($legacySaleKinds->isEmpty()) {
            return;
        }

        $settlementTypeIdsByCode = [];

        foreach (self::SETTLEMENT_CODES as $code) {
            $legacy = $legacySaleKinds->get($code);

            if ($legacy === null) {
                continue;
            }

            $existingId = DB::table('settlement_types')
                ->where('code', $code)
                ->where('is_system', true)
                ->whereNull('business_id')
                ->whereNull('deleted_at')
                ->value('id');

            if ($existingId !== null) {
                $settlementTypeIdsByCode[$code] = (int) $existingId;

                continue;
            }

            $settlementTypeIdsByCode[$code] = (int) DB::table('settlement_types')->insertGetId([
                'code' => $code,
                'name' => $legacy->name,
                'is_system' => true,
                'business_id' => null,
                'is_active' => (bool) $legacy->is_active,
                'sort_order' => (int) $legacy->sort_order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $saleKindIdToSettlementTypeId = [];

        foreach ($legacySaleKinds as $code => $legacy) {
            if (! isset($settlementTypeIdsByCode[$code])) {
                continue;
            }

            $saleKindIdToSettlementTypeId[(int) $legacy->id] = $settlementTypeIdsByCode[$code];
        }

        if ($saleKindIdToSettlementTypeId === []) {
            return;
        }

        $businessSaleKinds = DB::table('business_sale_kinds')
            ->whereIn('sale_kind_id', array_keys($saleKindIdToSettlementTypeId))
            ->get();

        foreach ($businessSaleKinds as $row) {
            $settlementTypeId = $saleKindIdToSettlementTypeId[(int) $row->sale_kind_id] ?? null;

            if ($settlementTypeId === null) {
                continue;
            }

            $exists = DB::table('business_settlement_types')
                ->where('business_id', $row->business_id)
                ->where('settlement_type_id', $settlementTypeId)
                ->exists();

            if (! $exists) {
                $hasDefault = DB::table('business_settlement_types')
                    ->where('business_id', $row->business_id)
                    ->where('is_default', true)
                    ->exists();

                DB::table('business_settlement_types')->insert([
                    'business_id' => $row->business_id,
                    'settlement_type_id' => $settlementTypeId,
                    'is_default' => (bool) $row->is_default && ! $hasDefault,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('business_sale_kinds')->where('id', $row->id)->delete();
        }

        foreach ($saleKindIdToSettlementTypeId as $saleKindId => $settlementTypeId) {
            DB::table('invoices')
                ->where('sale_kind_id', $saleKindId)
                ->whereNull('settlement_type_id')
                ->update([
                    'settlement_type_id' => $settlementTypeId,
                    'sale_kind_id' => null,
                    'updated_at' => $now,
                ]);

            if (Schema::hasColumn('quotes', 'sale_kind_id')) {
                DB::table('quotes')
                    ->where('sale_kind_id', $saleKindId)
                    ->whereNull('settlement_type_id')
                    ->update([
                        'settlement_type_id' => $settlementTypeId,
                        'sale_kind_id' => null,
                        'updated_at' => $now,
                    ]);
            }
        }

        DB::table('sale_kinds')
            ->whereIn('id', array_keys($saleKindIdToSettlementTypeId))
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
                'is_active' => false,
            ]);
    }
}
