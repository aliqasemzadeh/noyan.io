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
#[Fillable(['business_id', 'user_id', 'role', 'permissions'])]
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
            'role' => BusinessRole::class,
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

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->role->hasFullBusinessAccess()) {
            return BusinessPermission::values();
        }

        $permissions = $this->permissions;

        if (! is_array($permissions)) {
            return $this->role->defaultPermissions();
        }

        return array_values(array_intersect(
            BusinessPermission::values(),
            array_map('strval', $permissions),
        ));
    }

    public function hasPermission(BusinessPermission|string $permission): bool
    {
        if ($this->role->hasFullBusinessAccess()) {
            return true;
        }

        $name = $permission instanceof BusinessPermission
            ? $permission->value
            : $permission;

        return in_array($name, $this->permissionNames(), true);
    }

    public function canAccessPanel(BusinessPanel $panel): bool
    {
        if ($this->role->canAccessPanel($panel)) {
            return true;
        }

        if ($panel === BusinessPanel::Sale) {
            return $this->hasAnySalePermission();
        }

        return false;
    }

    public function hasAnySalePermission(): bool
    {
        foreach ($this->permissionNames() as $permission) {
            if (str_starts_with($permission, 'quote_')
                || str_starts_with($permission, 'invoice_')
                || str_starts_with($permission, 'party_')) {
                return true;
            }
        }

        return false;
    }
}
