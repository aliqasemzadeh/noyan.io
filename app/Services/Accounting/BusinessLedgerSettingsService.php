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
        $key = $this->cacheKey($businessId);
        $cached = Cache::get($key);

        if ($this->isCachePayload($cached)) {
            return $this->hydrate($cached);
        }

        if ($cached !== null) {
            Cache::forget($key);
        }

        $settings = $this->resolve($business, $businessId);

        Cache::put($key, $this->cachePayload($settings), now()->addHour());

        return $settings;
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

    protected function resolve(Business|int $business, int $businessId): BusinessLedgerSetting
    {
        $model = $business instanceof Business
            ? $business
            : Business::query()->find($businessId);

        if ($model === null) {
            return new BusinessLedgerSetting(BusinessLedgerSetting::defaultAttributes());
        }

        return BusinessLedgerSetting::forBusiness($model);
    }

    /**
     * Cache a plain array. The cache store refuses to unserialize PHP objects.
     *
     * @return array{attributes: array<string, mixed>, exists: bool}
     */
    protected function cachePayload(BusinessLedgerSetting $settings): array
    {
        return [
            'attributes' => $settings->getAttributes(),
            'exists' => $settings->exists,
        ];
    }

    /**
     * @param  array{attributes: array<string, mixed>, exists: bool}  $payload
     */
    protected function hydrate(array $payload): BusinessLedgerSetting
    {
        $settings = new BusinessLedgerSetting;
        $settings->setRawAttributes($payload['attributes'], true);
        $settings->exists = $payload['exists'];

        return $settings;
    }

    protected function isCachePayload(mixed $cached): bool
    {
        return is_array($cached)
            && isset($cached['attributes'], $cached['exists'])
            && is_array($cached['attributes'])
            && is_bool($cached['exists']);
    }
}
