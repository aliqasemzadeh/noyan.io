<?php

namespace App\Actions\Business;

use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Models\Business;
use Illuminate\Support\Str;

class UpdateBusinessAction
{
    /**
     * @param  array{name: string, type: string|BusinessType, category: string|BusinessCategory}  $data
     */
    public function handle(Business $business, array $data): Business
    {
        $type = $data['type'] instanceof BusinessType
            ? $data['type']
            : BusinessType::from($data['type']);

        $category = $data['category'] instanceof BusinessCategory
            ? $data['category']
            : BusinessCategory::from($data['category']);

        $nameChanged = $business->name !== $data['name'];

        $business->update([
            'name' => $data['name'],
            'type' => $type,
            'category' => $category,
            'slug' => $nameChanged
                ? $this->resolveUniqueSlug($data['name'], $business)
                : $business->slug,
        ]);

        $business->owner?->forgetBusinessesCache();

        return $business->fresh();
    }

    protected function resolveUniqueSlug(string $name, Business $business): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'business';
        }

        $candidate = $base;
        $suffix = 1;

        while (
            Business::withTrashed()
                ->where('slug', $candidate)
                ->where('id', '!=', $business->id)
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
