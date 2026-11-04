<?php

namespace App\Jobs\Accounting;

use App\Actions\Accounting\RebuildAccountingBalancesAction;
use App\Models\Business;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class RebuildAccountingBalancesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  list<int>|null  $businessIds
     */
    public function __construct(
        public ?array $businessIds = null,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        $key = $this->businessIds === null || $this->businessIds === []
            ? 'all'
            : implode('-', $this->businessIds);

        return [
            (new WithoutOverlapping('accounting-rebuild-balances:'.$key))->dontRelease(),
        ];
    }

    public function handle(RebuildAccountingBalancesAction $action): void
    {
        $businesses = $this->businessIds === null || $this->businessIds === []
            ? null
            : Business::query()->whereIn('id', $this->businessIds)->orderBy('id')->get();

        Log::info('Accounting balances rebuild job started.', [
            'business_ids' => $this->businessIds,
        ]);

        $stats = $action->handle($businesses);

        Log::info('Accounting balances rebuild job finished.', $stats);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Accounting balances rebuild job failed.', [
            'business_ids' => $this->businessIds,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
