<?php

namespace Tests\Unit;

use App\Actions\Business\ChargeBusinessSmsWalletAction;
use App\Actions\Business\DebitBusinessSmsWalletAction;
use App\Enums\Business\SmsWalletTransactionType;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SmsWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_charge_wallet(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create([
            'sms_wallet_balance' => 0,
        ]);

        $transaction = app(ChargeBusinessSmsWalletAction::class)->handle($business, '5000', $admin, 'top-up');

        $this->assertSame(SmsWalletTransactionType::Credit, $transaction->type);
        $this->assertSame('5000.00', (string) $business->fresh()->sms_wallet_balance);
        $this->assertSame('5000.00', (string) $transaction->balance_after);
    }

    public function test_debit_requires_sufficient_balance(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create([
            'sms_wallet_balance' => '1000',
        ]);

        $this->expectException(RuntimeException::class);

        app(DebitBusinessSmsWalletAction::class)->handle($business, '1500');
    }

    public function test_debit_reduces_balance(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create([
            'sms_wallet_balance' => '3000',
        ]);

        $transaction = app(DebitBusinessSmsWalletAction::class)->handle($business, '1000', 'sms');

        $this->assertSame(SmsWalletTransactionType::Debit, $transaction->type);
        $this->assertSame('2000.00', (string) $business->fresh()->sms_wallet_balance);
    }
}
