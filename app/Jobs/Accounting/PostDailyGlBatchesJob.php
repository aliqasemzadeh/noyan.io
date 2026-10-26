<?php

namespace App\Jobs\Accounting;

use App\Enums\Accounting\AutoPostQueueStatus;
use App\Models\Accounting\AutoPostQueue;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class PostDailyGlBatchesJob implements ShouldQueue
{
    use Queueable;

    public function handle(AutoPostingEngine $engine): void
    {
        $today = now('Asia/Tehran')->toDateString();

        AutoPostQueue::query()
            ->where('status', AutoPostQueueStatus::Pending)
            ->whereDate('entry_date', '<=', $today)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($engine): void {
                foreach ($rows as $row) {
                    $this->postRow($engine, $row);
                }
            });
    }

    protected function postRow(AutoPostingEngine $engine, AutoPostQueue $row): void
    {
        try {
            $source = $row->source;

            if ($source === null) {
                $row->update(['status' => AutoPostQueueStatus::Skipped]);

                return;
            }

            $user = $this->resolveUser($row);

            if ($user === null) {
                $row->update(['status' => AutoPostQueueStatus::Skipped]);

                return;
            }

            $entry = match (true) {
                $source instanceof Invoice => $engine->postInvoice($source, $user, forcePost: true),
                $source instanceof Transaction => $engine->postTransaction($source, $user, forcePost: true),
                default => null,
            };

            if ($entry === null) {
                $row->update(['status' => AutoPostQueueStatus::Skipped]);

                return;
            }

            $row->update([
                'status' => AutoPostQueueStatus::Posted,
                'journal_entry_id' => $entry->id,
            ]);
        } catch (Throwable $exception) {
            Log::error('Daily GL batch failed for queued document.', [
                'queue_id' => $row->id,
                'business_id' => $row->business_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function resolveUser(AutoPostQueue $row): ?User
    {
        $user = $row->requested_by !== null
            ? User::query()->find($row->requested_by)
            : null;

        if ($user !== null) {
            return $user;
        }

        $ownerId = Business::query()->whereKey($row->business_id)->value('owner_id');

        return $ownerId !== null ? User::query()->find($ownerId) : null;
    }
}
