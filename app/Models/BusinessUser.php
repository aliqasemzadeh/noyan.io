<?php

namespace App\Models;

use App\Enums\Business\BusinessPanel;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use Database\Factories\BusinessUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('business_user')]
#[Fillable(['business_id', 'user_id', 'roles', 'permissions'])]
class BusinessUser extends Model
{
    /** @use HasFactory<BusinessUserFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'permissions' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(BusinessRole::Owner);
    }

    public function hasRole(BusinessRole|string $role): bool
    {
        $value = $role instanceof BusinessRole ? $role->value : $role;

        return in_array($value, $this->roleValues(), true);
    }

    /**
     * @return list<BusinessRole>
     */
    public function roleEnums(): array
    {
        $roles = [];

        foreach ($this->roleValues() as $value) {
            $role = BusinessRole::tryFrom($value);

            if ($role !== null) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    public function roleValues(): array
    {
        $roles = $this->roles;

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $roles)));
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(BusinessRole|string $role): array
    {
        $roleValue = $role instanceof BusinessRole ? $role->value : $role;
        $roleEnum = $role instanceof BusinessRole ? $role : BusinessRole::tryFrom($roleValue);

        if ($roleEnum === BusinessRole::Owner || $this->isOwner()) {
            return BusinessPermission::values();
        }

        $map = $this->permissions;

        if (! is_array($map)) {
            return $roleEnum?->defaultPermissions() ?? [];
        }

        // Legacy flat list support during transition.
        if (array_is_list($map)) {
            return array_values(array_intersect(
                BusinessPermission::values(),
                array_map('strval', $map),
            ));
        }

        $rolePermissions = $map[$roleValue] ?? null;

        if (! is_array($rolePermissions)) {
            return $roleEnum?->defaultPermissions() ?? [];
        }

        $allowed = $roleEnum?->applicablePermissionValues() ?? BusinessPermission::values();

        return array_values(array_intersect(
            $allowed,
            array_map('strval', $rolePermissions),
        ));
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->isOwner()) {
            return BusinessPermission::values();
        }

        $permissions = [];

        foreach ($this->roleEnums() as $role) {
            $permissions = array_merge($permissions, $this->permissionsFor($role));
        }

        return array_values(array_unique($permissions));
    }

    public function hasPermission(BusinessPermission|string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $name = $permission instanceof BusinessPermission
            ? $permission->value
            : $permission;

        return in_array($name, $this->permissionNames(), true);
    }

    public function canAccessPanel(BusinessPanel $panel): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        foreach ($this->roleEnums() as $role) {
            if ($role->canAccessPanel($panel)) {
                return true;
            }
        }

        if ($panel === BusinessPanel::Sale) {
            return $this->hasAnySalePermission();
        }

        if ($panel === BusinessPanel::Accounting) {
            return $this->hasAnyAccountingPermission();
        }

        return false;
    }

    public function hasAnySalePermission(): bool
    {
        foreach ($this->permissionNames() as $permission) {
            $enum = BusinessPermission::tryFrom($permission);

            if ($enum?->isSaleRelated()) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyAccountingPermission(): bool
    {
        foreach ($this->permissionNames() as $permission) {
            $enum = BusinessPermission::tryFrom($permission);

            if ($enum !== null && ! $enum->isSaleRelated()) {
                return true;
            }
        }

        return false;
    }
}
