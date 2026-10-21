<?php

namespace App\Livewire\Forms;

use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class BusinessUserForm extends Form
{
    public ?Business $business = null;

    public ?BusinessUser $membership = null;

    public ?int $user_id = null;

    public string $role = 'viewer';

    /** @var list<string> */
    public array $permissions = [];

    public function setBusiness(Business $business): void
    {
        $this->business = $business;
        $this->membership = null;
        $this->reset(['user_id', 'role', 'permissions']);
        $this->role = BusinessRole::Viewer->value;
        $this->permissions = BusinessRole::Viewer->defaultPermissions();
    }

    public function setMembership(BusinessUser $membership): void
    {
        $this->membership = $membership;
        $this->business = $membership->business;
        $this->user_id = $membership->user_id;
        $this->role = $membership->role->value;
        $this->permissions = $membership->permissions ?? $membership->role->defaultPermissions();
    }

    public function syncPermissionsFromRole(): void
    {
        $role = BusinessRole::tryFrom($this->role);

        if ($role === null) {
            $this->permissions = [];

            return;
        }

        $this->permissions = $role->defaultPermissions();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userRules = [
            'required',
            'integer',
            'exists:users,id',
        ];

        if ($this->membership === null) {
            $userRules[] = function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->business === null || $value === null) {
                    return;
                }

                $exists = BusinessUser::query()
                    ->where('business_id', $this->business->id)
                    ->where('user_id', $value)
                    ->exists();

                if ($exists) {
                    $fail(__('general.business_user_already_member'));
                }
            };
        }

        return [
            'user_id' => $userRules,
            'role' => [
                'required',
                Rule::enum(BusinessRole::class)->except([BusinessRole::Owner]),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(BusinessPermission::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'user_id' => __('general.user'),
            'role' => __('general.role'),
            'permissions' => __('general.business_permissions'),
        ];
    }

    public function store(): BusinessUser
    {
        if ($this->business === null) {
            throw ValidationException::withMessages([
                'user_id' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();
        $permissions = $this->normalizedPermissions($validated['permissions'] ?? []);

        $membership = BusinessUser::withTrashed()
            ->where('business_id', $this->business->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($membership !== null) {
            if ($membership->trashed()) {
                $membership->restore();
            }

            $membership->update([
                'role' => $validated['role'],
                'permissions' => $permissions,
            ]);
        } else {
            $membership = BusinessUser::create([
                'business_id' => $this->business->id,
                'user_id' => $validated['user_id'],
                'role' => $validated['role'],
                'permissions' => $permissions,
            ]);
        }

        User::query()->find($validated['user_id'])?->forgetBusinessesCache();

        $this->reset(['user_id', 'role', 'permissions', 'membership']);
        $this->role = BusinessRole::Viewer->value;
        $this->permissions = BusinessRole::Viewer->defaultPermissions();

        return $membership;
    }

    public function update(): BusinessUser
    {
        if ($this->membership === null || $this->business === null) {
            throw ValidationException::withMessages([
                'role' => __('general.business_required'),
            ]);
        }

        if ($this->membership->role === BusinessRole::Owner) {
            throw ValidationException::withMessages([
                'role' => __('general.cannot_edit_business_owner'),
            ]);
        }

        $validated = $this->validate([
            'role' => [
                'required',
                Rule::enum(BusinessRole::class)->except([BusinessRole::Owner]),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(BusinessPermission::values())],
        ]);

        $this->membership->update([
            'role' => $validated['role'],
            'permissions' => $this->normalizedPermissions($validated['permissions'] ?? []),
        ]);

        $this->membership->user?->forgetBusinessesCache();

        $membership = $this->membership->fresh();

        $this->reset(['user_id', 'role', 'permissions', 'membership', 'business']);
        $this->role = BusinessRole::Viewer->value;
        $this->permissions = BusinessRole::Viewer->defaultPermissions();

        return $membership;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    protected function normalizedPermissions(array $permissions): array
    {
        return array_values(array_intersect(
            BusinessPermission::values(),
            array_map('strval', $permissions),
        ));
    }
}
