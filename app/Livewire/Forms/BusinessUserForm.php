<?php

namespace App\Livewire\Forms;

use App\Actions\Business\InviteBusinessUserAction;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;

class BusinessUserForm extends Form
{
    public ?Business $business = null;

    public ?BusinessUser $membership = null;

    public ?int $user_id = null;

    public string $mobile = '';

    /** @var list<string> */
    public array $roles = [];

    /** @var array<string, list<string>> */
    public array $permissions = [];

    public function setBusiness(Business $business): void
    {
        $this->business = $business;
        $this->membership = null;
        $this->reset(['user_id', 'mobile', 'roles', 'permissions']);
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
        $this->mobile = $membership->user?->mobile ?? '';
        $this->roles = $membership->roleValues();
        $this->permissions = $this->normalizePermissionsMap(
            $membership->permissions,
            $this->roles,
        );
    }

    public function resolveMembershipFromMobile(): void
    {
        $this->membership = null;
        $this->user_id = null;

        if ($this->business === null || $this->mobile === '') {
            return;
        }

        try {
            validator(
                ['mobile' => $this->mobile],
                ['mobile' => ['required', 'string', new IranianMobile(format: 'zero')]],
            )->validate();
        } catch (ValidationException) {
            return;
        }

        $membership = BusinessUser::query()
            ->with('user')
            ->where('business_id', $this->business->id)
            ->whereHas('user', fn ($query) => $query->where('mobile', $this->mobile))
            ->first();

        if ($membership === null || $membership->isOwner()) {
            return;
        }

        $this->membership = $membership;
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
        return [
            'mobile' => ['required', 'string', new IranianMobile(format: 'zero')],
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
            'mobile' => __('general.mobile'),
            'roles' => __('general.roles'),
            'permissions' => __('general.business_permissions'),
        ];
    }

    public function saveOrInvite(InviteBusinessUserAction $inviteAction): BusinessUser|BusinessInvitation
    {
        if ($this->business === null) {
            throw ValidationException::withMessages([
                'mobile' => __('general.business_required'),
            ]);
        }

        $this->syncPermissionsFromRoles();
        $validated = $this->validate();
        $roles = $this->normalizedRoles($validated['roles'] ?? []);
        $permissions = $this->normalizePermissionsMap($validated['permissions'] ?? [], $roles);
        $mobile = $validated['mobile'];

        $membership = BusinessUser::query()
            ->with('user')
            ->where('business_id', $this->business->id)
            ->whereHas('user', fn ($query) => $query->where('mobile', $mobile))
            ->first();

        if ($membership !== null) {
            if ($membership->isOwner()) {
                throw ValidationException::withMessages([
                    'mobile' => __('general.cannot_edit_business_owner'),
                ]);
            }

            $this->membership = $membership;
            $this->user_id = $membership->user_id;

            $membership->update([
                'roles' => $roles,
                'permissions' => $permissions,
            ]);

            $membership->user?->forgetBusinessesCache();

            $fresh = $membership->fresh() ?? $membership;
            $this->resetFormState();

            return $fresh;
        }

        $inviter = Auth::user();

        if (! $inviter instanceof User) {
            throw ValidationException::withMessages([
                'mobile' => __('general.business_required'),
            ]);
        }

        $invitation = $inviteAction->handle(
            $this->business,
            $mobile,
            $roles,
            $permissions,
            $inviter,
        );

        $this->resetFormState();

        return $invitation;
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

        $this->reset(['user_id', 'mobile', 'roles', 'permissions', 'membership', 'business']);
        $this->roles = [BusinessRole::Viewer->value];
        $this->permissions = [
            BusinessRole::Viewer->value => BusinessRole::Viewer->defaultPermissions(),
        ];

        return $membership;
    }

    protected function resetFormState(): void
    {
        $this->reset(['user_id', 'mobile', 'roles', 'permissions', 'membership']);
        $this->roles = [BusinessRole::Viewer->value];
        $this->permissions = [
            BusinessRole::Viewer->value => BusinessRole::Viewer->defaultPermissions(),
        ];
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
