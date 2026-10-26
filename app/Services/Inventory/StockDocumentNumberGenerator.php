<?php

namespace App\Services\Inventory;

use App\Enums\Catalog\StockDocumentType;
use App\Models\Catalog\StockCount;
use App\Models\Catalog\StockDocument;
use Illuminate\Database\Eloquent\Builder;
use Morilog\Jalali\Jalalian;

class StockDocumentNumberGenerator
{
    public function nextDocumentNumber(int $businessId, StockDocumentType $type): string
    {
        return $this->next(
            $type->numberPrefix(),
            StockDocument::query()->where('business_id', $businessId)->where('type', $type->value),
        );
    }

    public function nextCountNumber(int $businessId): string
    {
        return $this->next(
            'SC',
            StockCount::query()->where('business_id', $businessId),
        );
    }

    /**
     * @param  Builder<StockDocument>|Builder<StockCount>  $query
     */
    protected function next(string $prefix, Builder $query): string
    {
        $period = Jalalian::now()->format('Ym');
        $pattern = $prefix.'-'.$period.'-';

        $latest = $query
            ->where('number', 'like', $pattern.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if (is_string($latest)) {
            $sequence = substr($latest, strlen($pattern));

            if (ctype_digit($sequence)) {
                $next = ((int) $sequence) + 1;
            }
        }

        return $pattern.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
