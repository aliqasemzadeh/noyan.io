<?php

namespace Tests\Unit;

use App\Models\Accounting\Invoice;
use App\Models\Business;
use App\Models\User;
use App\Services\Notifications\InvoicePlaceholderRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePlaceholderRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_placeholders(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create(['name' => 'Noyan Co']);
        $invoice = Invoice::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'SL-1405-01-0009',
            'party_name' => 'Acme',
            'total_amount' => '150000',
            'paid_amount' => '50000',
        ]);

        $rendered = app(InvoicePlaceholderRenderer::class)->render(
            'Hello {client_name} from {company_name} #{invoice_number} amount {amount} balance {balance}',
            $invoice,
            ['link' => 'https://example.test/i/abc'],
        );

        $this->assertStringContainsString('Acme', $rendered);
        $this->assertStringContainsString('Noyan Co', $rendered);
        $this->assertStringContainsString('SL-1405-01-0009', $rendered);
        $this->assertStringContainsString('150,000', $rendered);
        $this->assertStringContainsString('100,000', $rendered);
    }
}
