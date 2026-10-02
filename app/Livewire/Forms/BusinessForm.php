<?php

namespace App\Livewire\Forms;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class BusinessForm extends Form
{
    public ?Business $business = null;

    public string $name = '';

    public string $slug = '';

    public ?int $owner_id = null;

    public bool $is_active = true;

    public function setModel(Business $business): void
    {
        $this->business = $business;
        $this->name = $business->name;
        $this->slug = $business->slug;
        $this->owner_id = $business->owner_id;
        $this->is_active = $business->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('businesses', 'slug')->ignore($this->business?->id),
            ],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'slug' => __('general.slug'),
            'owner_id' => __('general.owner'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): Business
    {
        $validated = $this->validate();
        $validated['slug'] = $this->resolveSlug($validated['slug'] ?? null, $validated['name']);

        $previousOwnerId = null;

        $business = Business::create($validated);

        $this->syncOwnerMembership($business, $previousOwnerId);
        $this->ensureOwnerCurrentBusiness($business);

        $owner = User::query()->find($business->owner_id);
        $owner?->forgetBusinessesCache();

        $this->reset();

        return $business;
    }

    public function update(): void
    {
        $validated = $this->validate();
        $validated['slug'] = $this->resolveSlug($validated['slug'] ?? null, $validated['name']);

        $previousOwnerId = $this->business?->owner_id;

        $this->business->update($validated);

        $this->syncOwnerMembership($this->business->fresh(), $previousOwnerId);
        $this->ensureOwnerCurrentBusiness($this->business->fresh());

        User::query()->find($previousOwnerId)?->forgetBusinessesCache();
        User::query()->find($this->business->owner_id)?->forgetBusinessesCache();

        $this->reset();
    }

    protected function resolveSlug(?string $slug, string $name): string
    {
        $base = filled($slug) ? Str::slug($slug) : Str::slug($name);

        if ($base === '') {
            $base = 'business';
        }

        $candidate = $base;
        $suffix = 1;

        while (
            Business::withTrashed()
                ->where('slug', $candidate)
                ->when($this->business, fn ($query) => $query->where('id', '!=', $this->business->id))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function syncOwnerMembership(Business $business, ?int $previousOwnerId): void
    {
        $ownerId = (int) $business->owner_id;

        if ($previousOwnerId !== null && $previousOwnerId !== $ownerId) {
            $previousMembership = BusinessUser::withTrashed()
                ->where('business_id', $business->id)
                ->where('user_id', $previousOwnerId)
                ->first();

            if ($previousMembership !== null) {
                if ($previousMembership->trashed()) {
                    $previousMembership->restore();
                }

                $previousMembership->update([
                    'role' => BusinessRole::Admin,
                ]);
            }
        }

        $membership = BusinessUser::withTrashed()
            ->where('business_id', $business->id)
            ->where('user_id', $ownerId)
            ->first();

        if ($membership !== null) {
            if ($membership->trashed()) {
                $membership->restore();
            }

            $membership->update([
                'role' => BusinessRole::Owner,
            ]);

            return;
        }

        BusinessUser::create([
            'business_id' => $business->id,
            'user_id' => $ownerId,
            'role' => BusinessRole::Owner,
        ]);
    }

    protected function ensureOwnerCurrentBusiness(Business $business): void
    {
        $owner = User::query()->find($business->owner_id);

        if ($owner !== null && $owner->current_business_id === null) {
            $owner->forceFill([
                'current_business_id' => $business->id,
            ])->save();
        }
    }
}
