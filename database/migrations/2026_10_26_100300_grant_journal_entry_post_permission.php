<?php

use App\Enums\Business\BusinessPermission;
use App\Models\BusinessUser;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->sync(add: true);
    }

    public function down(): void
    {
        $this->sync(add: false);
    }

    protected function sync(bool $add): void
    {
        $permission = BusinessPermission::JournalEntryPost->value;

        BusinessUser::query()->orderBy('id')->each(function (BusinessUser $membership) use ($permission, $add): void {
            $map = $membership->permissions;

            if (! is_array($map)) {
                return;
            }

            $changed = false;

            if (array_is_list($map)) {
                $next = $this->adjustList($map, $permission, $add);
                if ($next !== $map) {
                    $map = $next;
                    $changed = true;
                }
            } else {
                foreach ($map as $role => $permissions) {
                    if (! is_array($permissions)) {
                        continue;
                    }

                    $next = $this->adjustList($permissions, $permission, $add);

                    if ($next !== array_values($permissions)) {
                        $map[$role] = $next;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $membership->permissions = $map;
                $membership->save();
            }
        });
    }

    /**
     * @param  list<mixed>  $permissions
     * @return list<string>
     */
    protected function adjustList(array $permissions, string $permission, bool $add): array
    {
        $values = array_values(array_map('strval', $permissions));

        if ($add) {
            if (in_array('journal_entry_edit', $values, true) && ! in_array($permission, $values, true)) {
                $values[] = $permission;
            }

            return $values;
        }

        return array_values(array_filter(
            $values,
            fn (string $value): bool => $value !== $permission,
        ));
    }
};
