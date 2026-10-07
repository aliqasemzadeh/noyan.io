<?php

namespace App\Actions\Business;

use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UpdateBusinessAction
{
    /**
     * @param  array{
     *     name: string,
     *     type: string|BusinessType,
     *     category: string|BusinessCategory,
     *     phone?: string|null,
     *     address?: string|null,
     *     invoice_primary_color?: string|null,
     *     invoice_secondary_color?: string|null,
     *     logo?: TemporaryUploadedFile|UploadedFile|null,
     *     remove_logo?: bool
     * }  $data
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
            'phone' => filled($data['phone'] ?? null) ? $data['phone'] : null,
            'address' => filled($data['address'] ?? null) ? $data['address'] : null,
            'invoice_primary_color' => filled($data['invoice_primary_color'] ?? null)
                ? $data['invoice_primary_color']
                : null,
            'invoice_secondary_color' => filled($data['invoice_secondary_color'] ?? null)
                ? $data['invoice_secondary_color']
                : null,
            'slug' => $nameChanged
                ? $this->resolveUniqueSlug($data['name'], $business)
                : $business->slug,
        ]);

        $this->syncLogo($business, $data['logo'] ?? null, (bool) ($data['remove_logo'] ?? false));

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

    protected function syncLogo(
        Business $business,
        TemporaryUploadedFile|UploadedFile|null $logo,
        bool $removeLogo,
    ): void {
        if ($logo !== null) {
            $business
                ->addMedia($logo)
                ->toMediaCollection('logo');

            return;
        }

        if ($removeLogo) {
            $business->clearMediaCollection('logo');
        }
    }
}
