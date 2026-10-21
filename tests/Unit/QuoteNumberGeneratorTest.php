<?php

namespace Tests\Unit;

use App\Models\Accounting\Quote;
use App\Models\Business;
use App\Models\User;
use App\Services\QuoteNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Morilog\Jalali\Jalalian;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_and_increments_quote_numbers(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $at = Jalalian::fromFormat('Y-m-d', '1405-07-15');

        $first = app(QuoteNumberGenerator::class)->generate($business->id, $at);

        Quote::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'quote_number' => $first,
        ]);

        $second = app(QuoteNumberGenerator::class)->generate($business->id, $at);

        $this->assertSame('QT-1405-07-0001', $first);
        $this->assertSame('QT-1405-07-0002', $second);
    }

    #[Test]
    public function it_ignores_soft_deleted_numbers(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $at = Jalalian::fromFormat('Y-m-d', '1405-07-15');

        Quote::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'quote_number' => 'QT-1405-07-0003__del_99',
        ]);

        $next = app(QuoteNumberGenerator::class)->generate($business->id, $at);

        $this->assertSame('QT-1405-07-0001', $next);
    }

    #[Test]
    public function it_keeps_sequences_per_business(): void
    {
        $user = User::factory()->create();
        $businessA = Business::factory()->for($user, 'owner')->create();
        $businessB = Business::factory()->for($user, 'owner')->create();
        $at = Jalalian::fromFormat('Y-m-d', '1405-07-15');

        Quote::factory()->create([
            'business_id' => $businessA->id,
            'created_by' => $user->id,
            'quote_number' => 'QT-1405-07-0005',
        ]);

        $forB = app(QuoteNumberGenerator::class)->generate($businessB->id, $at);

        $this->assertSame('QT-1405-07-0001', $forB);
    }
}
