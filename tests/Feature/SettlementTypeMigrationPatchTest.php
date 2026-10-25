<?php

namespace Tests\Feature;

use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\SaleKind;
use App\Models\Business;
use App\Models\User;
use App\Support\Accounting\SplitSaleKindsIntoSettlementTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettlementTypeMigrationPatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_settlement_sale_kinds_are_moved_to_settlement_types(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();

        $cashSaleKindId = DB::table('sale_kinds')->insertGetId([
            'code' => 'cash',
            'name' => 'نقد',
            'is_system' => true,
            'business_id' => null,
            'is_active' => true,
            'sort_order' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('business_sale_kinds')->insert([
            'business_id' => $business->id,
            'sale_kind_id' => $cashSaleKindId,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'SL-TEST-1',
            'type' => InvoiceType::Sale,
            'sale_kind_id' => $cashSaleKindId,
            'issue_date' => now()->toDateString(),
            'items_total' => '0',
            'global_discount' => '0',
            'global_tax' => '0',
            'total_amount' => '0',
            'paid_amount' => '0',
            'payment_status' => 'unpaid',
        ]);

        (new SplitSaleKindsIntoSettlementTypes)->handle();

        $this->assertDatabaseHas('settlement_types', [
            'code' => 'cash',
            'is_system' => true,
            'business_id' => null,
        ]);

        $settlementTypeId = (int) DB::table('settlement_types')
            ->where('code', 'cash')
            ->where('is_system', true)
            ->value('id');

        $this->assertDatabaseHas('business_settlement_types', [
            'business_id' => $business->id,
            'settlement_type_id' => $settlementTypeId,
            'is_default' => true,
        ]);

        $this->assertDatabaseMissing('business_sale_kinds', [
            'business_id' => $business->id,
            'sale_kind_id' => $cashSaleKindId,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'sale_kind_id' => null,
            'settlement_type_id' => $settlementTypeId,
        ]);

        $this->assertNotNull(SaleKind::withTrashed()->find($cashSaleKindId)?->deleted_at);
    }
}
