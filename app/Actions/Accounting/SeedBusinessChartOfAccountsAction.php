<?php

namespace App\Actions\Accounting;

use App\Enums\Accounting\AccountLevel;
use App\Enums\Accounting\AccountNature;
use App\Enums\Accounting\ChartTemplate;
use App\Enums\Accounting\DetailType;
use App\Enums\Accounting\StatementType;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class SeedBusinessChartOfAccountsAction
{
    /**
     * Upsert the standard chart. Safe to run repeatedly — uses business_id + full_code
     * and never creates duplicate rows. Soft-deleted system rows are restored.
     *
     * @return list<GlAccount>
     */
    public function handle(Business $business, bool $force = false, ?ChartTemplate $template = null): array
    {
        unset($force);

        $definitions = ($template ?? ChartTemplate::Trading)->definitions();

        return DB::transaction(function () use ($business, $definitions): array {
            $created = [];

            foreach ($definitions as $index => $group) {
                $created = array_merge(
                    $created,
                    $this->seedNode($business, $group, null, AccountLevel::Group, $index),
                );
            }

            return $created;
        });
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     nature: AccountNature,
     *     statement_type: StatementType,
     *     system_key?: string|null,
     *     requires_detail_level_4_type?: DetailType|null,
     *     children?: list<array<string, mixed>>
     * }  $node
     * @return list<GlAccount>
     */
    protected function seedNode(
        Business $business,
        array $node,
        ?GlAccount $parent,
        AccountLevel $level,
        int $sortOrder,
    ): array {
        $fullCode = $parent === null ? $node['code'] : $parent->full_code.$node['code'];
        $isSubsidiary = $level === AccountLevel::Subsidiary;
        $systemKey = $node['system_key'] ?? null;

        if (filled($systemKey)) {
            GlAccount::withTrashed()
                ->where('business_id', $business->id)
                ->where('system_key', $systemKey)
                ->where('full_code', '!=', $fullCode)
                ->update(['system_key' => null]);
        }

        $account = GlAccount::withTrashed()->firstOrNew([
            'business_id' => $business->id,
            'full_code' => $fullCode,
        ]);

        $account->fill([
            'parent_id' => $parent?->id,
            'level' => $level,
            'code' => $node['code'],
            'name' => $node['name'],
            'nature' => $node['nature'],
            'statement_type' => $node['statement_type'],
            'is_postable' => $isSubsidiary,
            'is_system' => true,
            'system_key' => $systemKey,
            'requires_detail_level_4_type' => $node['requires_detail_level_4_type'] ?? null,
            'requires_detail_level_5_type' => $node['requires_detail_level_5_type'] ?? null,
            'requires_detail_level_6_type' => $node['requires_detail_level_6_type'] ?? null,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);

        if ($account->trashed()) {
            $account->restore();
        } else {
            $account->save();
        }

        $created = [$account];
        $childLevel = $level->childLevel();

        if ($childLevel === null) {
            return $created;
        }

        foreach ($node['children'] ?? [] as $childIndex => $child) {
            $created = array_merge(
                $created,
                $this->seedNode($business, $child, $account, $childLevel, $childIndex),
            );
        }

        return $created;
    }
}
