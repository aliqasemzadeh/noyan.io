<?php

namespace Tests\Unit;

use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_prefixed_jalali_sequence(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $jalali = Jalalian::fromFormat('Y-m-d', '1405-01-15');

        $generator = app(InvoiceNumberGenerator::class);

        $first = $generator->generate($business->id, InvoiceType::Sale, $jalali);
        $this->assertSame('SL-1405-01-0001', $first);

        Invoice::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'type' => InvoiceType::Sale,
            'invoice_number' => $first,
        ]);

        $second = $generator->generate($business->id, InvoiceType::Sale, $jalali);
        $this->assertSame('SL-1405-01-0002', $second);
    }

    public function test_purchase_uses_pr_prefix(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $jalali = Jalalian::fromFormat('Y-m-d', '1405-02-01');

        $number = app(InvoiceNumberGenerator::class)->generate($business->id, InvoiceType::Purchase, $jalali);

        $this->assertSame('PR-1405-02-0001', $number);
    }
}
