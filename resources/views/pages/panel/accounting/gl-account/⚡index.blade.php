<?php

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Enums\Accounting\AccountLevel;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public string $levelFilter = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
    }

    public function updatedSearch(): void
    {
        unset($this->treeRows);
    }

    public function updatedLevelFilter(): void
    {
        unset($this->treeRows);
    }

    public function updatedStatusFilter(): void
    {
        unset($this->treeRows);
    }

    #[On('panels.accounting.gl-account.index.table')]
    public function refreshTable(): void
    {
        unset($this->treeRows);
    }

    public function seedStandardChart(SeedBusinessChartOfAccountsAction $action): void
    {
        $businessId = Auth::user()?->current_business_id;
        abort_if($businessId === null, 403);

        $business = Business::query()->findOrFail($businessId);
        $accounts = $action->handle($business, force: true);

        unset($this->treeRows);

        Flux::toast(__('general.gl_account_seeded', ['count' => count($accounts)]));
    }

    /**
     * @return list<array{account: GlAccount, depth: int}>
     */
    #[Computed]
    public function treeRows(): array
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return [];
        }

        /** @var Collection<int, GlAccount> $accounts */
        $accounts = GlAccount::query()
            ->where('business_id', $businessId)
            ->orderBy('full_code')
            ->get()
            ->keyBy('id');

        $search = mb_strtolower(trim($this->search));
        $filtering = $search !== '' || $this->levelFilter !== '' || $this->statusFilter !== '';

        $matchIds = [];

        if ($filtering) {
            foreach ($accounts as $account) {
                if ($this->levelFilter !== '' && (string) $account->level->value !== $this->levelFilter) {
                    continue;
                }

                if ($this->statusFilter === 'active' && ! $account->is_active) {
                    continue;
                }

                if ($this->statusFilter === 'inactive' && $account->is_active) {
                    continue;
                }

                if ($search !== '') {
                    $haystack = mb_strtolower($account->full_code.' '.$account->name.' '.($account->system_key ?? ''));

                    if (! str_contains($haystack, $search)) {
                        continue;
                    }
                }

                $matchIds[$account->id] = true;
                $parentId = $account->parent_id;

                while ($parentId !== null && isset($accounts[$parentId])) {
                    $matchIds[$parentId] = true;
                    $parentId = $accounts[$parentId]->parent_id;
                }
            }
        }

        $rows = [];

        $walk = function (?int $parentId, int $depth) use (&$walk, $accounts, $filtering, $matchIds, &$rows): void {
            $children = $accounts
                ->filter(fn (GlAccount $account): bool => $account->parent_id === $parentId)
                ->sortBy(['sort_order', 'full_code']);

            foreach ($children as $account) {
                if ($filtering && ! isset($matchIds[$account->id])) {
                    continue;
                }

                $rows[] = ['account' => $account, 'depth' => $depth];
                $walk($account->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $rows;
    }
};
?>

<x-slot name="title">{{ __('general.chart_of_accounts') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.chart_of_accounts') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.chart_of_accounts') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.chart_of_accounts_hint') }}</flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button
                    variant="primary"
                    color="zinc"
                    icon="refresh-cw"
                    wire:click="seedStandardChart"
                    wire:confirm="{{ __('general.gl_account_seed_confirm') }}"
                >
                    {{ __('general.seed_standard_coa') }}
                </flux:button>

                <flux:modal.trigger name="gl-account.create">
                    <flux:button
                        variant="primary"
                        color="teal"
                        icon="plus"
                        @click="$dispatch('panels.accounting.gl-account.create.assign-data', { parentId: null })"
                    >
                        {{ __('general.create_gl_account') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('general.search')"
                icon="search"
                clearable
            />

            <flux:select wire:model.live="levelFilter" variant="listbox">
                <flux:select.option value="">{{ __('general.all_levels') }}</flux:select.option>
                @foreach (AccountLevel::cases() as $level)
                    <flux:select.option :value="(string) $level->value">{{ $level->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" variant="listbox">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('general.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('general.inactive') }}</flux:select.option>
            </flux:select>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('general.full_code') }}</flux:table.column>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.account_level') }}</flux:table.column>
                <flux:table.column>{{ __('general.account_nature') }}</flux:table.column>
                <flux:table.column>{{ __('general.status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->treeRows as $row)
                    @php($account = $row['account'])
                    <flux:table.row :key="'gl-'.$account->id">
                        <flux:table.cell>
                            <span class="font-mono" dir="ltr" style="padding-inline-start: {{ $row['depth'] * 1.25 }}rem">
                                {{ $account->full_code }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-2">
                                <span>{{ $account->name }}</span>
                                @if ($account->is_system)
                                    <flux:badge size="sm" color="zinc">{{ __('general.system') }}</flux:badge>
                                @endif
                                @if ($account->is_postable)
                                    <flux:badge size="sm" color="teal">{{ __('general.postable') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $account->level->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $account->nature->label() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$account->is_active ? 'green' : 'zinc'">
                                {{ $account->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @if ($account->canHaveChildren())
                                    <flux:tooltip content="{{ __('general.add_child_account') }}">
                                        <flux:modal.trigger name="gl-account.create">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="teal"
                                                icon="plus"
                                                icon:variant="outline"
                                                @click="$dispatch('panels.accounting.gl-account.create.assign-data', { parentId: {{ $account->id }} })"
                                            />
                                        </flux:modal.trigger>
                                    </flux:tooltip>
                                @endif

                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:modal.trigger name="gl-account.edit">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="amber"
                                            icon="pencil"
                                            icon:variant="outline"
                                            @click="$dispatch('panels.accounting.gl-account.edit.assign-data', { glAccountId: {{ $account->id }} })"
                                        />
                                    </flux:modal.trigger>
                                </flux:tooltip>

                                @unless ($account->is_system)
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:modal.trigger name="gl-account.delete">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="red"
                                                icon="trash"
                                                icon:variant="outline"
                                                @click="$dispatch('panels.accounting.gl-account.delete.assign-data', { glAccountId: {{ $account->id }} })"
                                            />
                                        </flux:modal.trigger>
                                    </flux:tooltip>
                                @endunless
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text>{{ __('general.no_gl_accounts') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.gl-account.create :key="'gl-account-create'" />
    <livewire:accounting.gl-account.edit :key="'gl-account-edit'" />
    <livewire:accounting.gl-account.delete :key="'gl-account-delete'" />
</div>
