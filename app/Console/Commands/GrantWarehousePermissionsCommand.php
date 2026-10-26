<?php

namespace App\Console\Commands;

use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\BusinessUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:grant-warehouse-permissions')]
#[Description('Add warehouse count and inventory-value permissions to stored business role lists')]
class GrantWarehousePermissionsCommand extends Command
{
    public function handle(): int
    {
        $updated = 0;

        BusinessUser::query()->orderBy('id')->each(function (BusinessUser $member) use (&$updated): void {
            if ($this->grant($member)) {
                $updated++;
            }
        });

        $this->info("Granted warehouse permissions on {$updated} memberships.");

        return self::SUCCESS;
    }

    protected function grant(BusinessUser $member): bool
    {
        if ($member->isOwner()) {
            return false;
        }

        $map = $member->permissions;

        if (! is_array($map)) {
            return false;
        }

        if (array_is_list($map)) {
            return $this->grantFlatList($member, $map);
        }

        $changed = false;

        foreach ($member->roleEnums() as $role) {
            $grants = $this->grantsForRole($role);

            if ($grants === [] || ! array_key_exists($role->value, $map) || ! is_array($map[$role->value])) {
                continue;
            }

            $current = array_values(array_map('strval', $map[$role->value]));
            $next = array_values(array_unique([...$current, ...$grants]));

            if ($next === $current) {
                continue;
            }

            $map[$role->value] = $next;
            $changed = true;
        }

        if (! $changed) {
            return false;
        }

        $member->forceFill(['permissions' => $map])->save();

        return true;
    }

    /**
     * @param  list<mixed>  $permissions
     */
    protected function grantFlatList(BusinessUser $member, array $permissions): bool
    {
        $grants = [];

        foreach ($member->roleEnums() as $role) {
            $grants = [...$grants, ...$this->grantsForRole($role)];
        }

        $grants = array_values(array_unique($grants));

        if ($grants === []) {
            return false;
        }

        $current = array_values(array_map('strval', $permissions));
        $next = array_values(array_unique([...$current, ...$grants]));

        if ($next === $current) {
            return false;
        }

        $member->forceFill(['permissions' => $next])->save();

        return true;
    }

    /**
     * @return list<string>
     */
    protected function grantsForRole(BusinessRole $role): array
    {
        return match ($role) {
            BusinessRole::WarehouseKeeper => [BusinessPermission::StockCount->value],
            BusinessRole::Accountant => [BusinessPermission::StockViewValue->value],
            BusinessRole::Admin => [
                BusinessPermission::StockCount->value,
                BusinessPermission::StockViewValue->value,
            ],
            default => [],
        };
    }
}
