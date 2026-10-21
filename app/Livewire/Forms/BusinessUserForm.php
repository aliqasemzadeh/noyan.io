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

    /** @var list<string> */
    public array $roles = [];

    /** @var array<string, list<string>> */
    public array $permissions = [];

    public function setBusiness(Business $business): void
    {
        $this->business = $business;
        $this->membership = null;
        $this->reset(['user_id', 'roles', 'permissions']);
        $this->roles = [BusinessRole::Viewer->value];
        $this->permissions = [
            BusinessRole::Viewer->value => BusinessRole::Viewer->defaultPermissions(),
        ];
    }

    public function setMembership(BusinessUser $membership): void
    {
        $this->membership = $membership;
        $this->business = $membership->business;
        $this->user_id = $membership->user_id;
        $this->roles = $membership->roleValues();
        $this->permissions = $this->normalizePermissionsMap(
            $membership->permissions,
            $this->roles,
        );
    }

    public function syncPermissionsFromRoles(): void
    {
        $selected = $this->normalizedRoles($this->roles);
        $next = [];

        foreach ($selected as $roleValue) {
            $role = BusinessRole::from($roleValue);
            $next[$roleValue] = $this->permissions[$roleValue]
                ?? $role->defaultPermissions();
            $next[$roleValue] = array_values(array_intersect(
                $role->applicablePermissionValues(),
                array_map('strval', $next[$roleValue]),
            ));
        }

        $this->roles = $selected;
        $this->permissions = $next;
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
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::enum(BusinessRole::class)->except([BusinessRole::Owner]),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string', Rule::in(BusinessPermission::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'user_id' => __('general.user'),
            'roles' => __('general.roles'),
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
        $roles = $this->normalizedRoles($validated['roles'] ?? []);
        $permissions = $this->normalizePermissionsMap($validated['permissions'] ?? [], $roles);

        $membership = BusinessUser::withTrashed()
            ->where('business_id', $this->business->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($membership !== null) {
            if ($membership->trashed()) {
                $membership->restore();
            }

            $membership->update([
                'roles' => $roles,
                'permissions' => $permissions,
            ]);
        } else {
            $membership = BusinessUser::create([
                'business_id' => $this->business->id,
                'user_id' => $validated['user_id'],
                'roles' => $roles,
                'permissions' => $permissions,
            ]);
        }

        User::query()->find($validated['user_id'])?->forgetBusinessesCache();

        $this->reset(['user_id', 'roles', 'permissions', 'membership']);
        $this->roles = [BusinessRole::Viewer->value];
        $this->permissions = [
            BusinessRole::Viewer->value => BusinessRole::Viewer->defaultPermissions(),
        ];

        return $membership;
    }

    public function update(): BusinessUser
    {
        if ($this->membership === null || $this->business === null) {
            throw ValidationException::withMessages([
                'roles' => __('general.business_required'),
            ]);
        }

        if ($this->membership->isOwner()) {
            throw ValidationException::withMessages([
                'roles' => __('general.cannot_edit_business_owner'),
            ]);
        }

        $validated = $this->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::enum(BusinessRole::class)->except([BusinessRole::Owner]),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string', Rule::in(BusinessPermission::values())],
        ]);

        $roles = $this->normalizedRoles($validated['roles'] ?? []);
        $permissions = $this->normalizePermissionsMap($validated['permissions'] ?? [], $roles);

        $this->membership->update([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);

        $this->membership->user?->forgetBusinessesCache();

        $membership = $this->membership->fresh();

        $this->reset(['user_id', 'roles', 'permissions', 'membership', 'business']);
        $this->roles = [BusinessRole::Viewer->value];
        $this->permissions = [
            BusinessRole::Viewer->value => BusinessRole::Viewer->defaultPermissions(),
        ];

        return $membership;
    }

    /**
     * @param  list<string>|mixed  $roles
     * @return list<string>
     */
    protected function normalizedRoles(mixed $roles): array
    {
        if (! is_array($roles)) {
            return [];
        }

        $assignable = array_column(BusinessRole::assignable(), 'value');

        return array_values(array_unique(array_filter(
            array_map('strval', $roles),
            fn (string $role): bool => in_array($role, $assignable, true),
        )));
    }

    /**
     * @param  mixed  $permissions
     * @param  list<string>  $roles
     * @return array<string, list<string>>
     */
    protected function normalizePermissionsMap(mixed $permissions, array $roles): array
    {
        $map = is_array($permissions) ? $permissions : [];
        $normalized = [];

        foreach ($roles as $roleValue) {
            $role = BusinessRole::tryFrom($roleValue);

            if ($role === null) {
                continue;
            }

            $rolePermissions = $map[$roleValue] ?? $role->defaultPermissions();

            if (! is_array($rolePermissions)) {
                $rolePermissions = $role->defaultPermissions();
            }

            $normalized[$roleValue] = array_values(array_intersect(
                $role->applicablePermissionValues(),
                array_map('strval', $rolePermissions),
            ));
        }

        return $normalized;
    }
}
