<?php

namespace App\Services\Accounting;

use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use Illuminate\Support\Facades\Cache;

class BusinessLedgerSettingsService
{
    public function get(Business|int $business): BusinessLedgerSetting
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return Cache::remember(
            $this->cacheKey($businessId),
            now()->addHour(),
            function () use ($business, $businessId): BusinessLedgerSetting {
                $model = $business instanceof Business
                    ? $business
                    : Business::query()->find($businessId);

                if ($model === null) {
                    return new BusinessLedgerSetting(BusinessLedgerSetting::defaultAttributes());
                }

                return BusinessLedgerSetting::forBusiness($model);
            },
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Business $business, array $data): BusinessLedgerSetting
    {
        $settings = BusinessLedgerSetting::forBusiness($business);
        $settings->fill($data);
        $settings->save();

        $this->forget($business->id);

        return $settings->fresh() ?? $settings;
    }

    public function forget(int $businessId): void
    {
        Cache::forget($this->cacheKey($businessId));
    }

    public function cacheKey(int $businessId): string
    {
        return "business:{$businessId}:ledger_settings";
    }
}
