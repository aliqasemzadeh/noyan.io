<?php

use App\Enums\CategoryType;
use App\Livewire\Concerns\WithCategoryTreeListing;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithCategoryTreeListing;
    use WithPagination;

    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $statusFilter = '';

    public bool $typeLocked = false;

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if ($this->type === '') {
            $this->type = request()->routeIs('accounting.catalog.categories.*')
                ? CategoryType::Product->value
                : CategoryType::Expense->value;
        }

        $this->typeLocked = request()->routeIs('accounting.catalog.categories.*');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->forgetCategoryTreeComputed();
    }

    public function updatedType(): void
    {
        $this->resetPage();
        $this->forgetCategoryTreeComputed();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->forgetCategoryTreeComputed();
    }

    #[On('panels.accounting.category.index.table')]
    public function refreshTable(): void
    {
        $this->forgetCategoryTreeComputed();
    }

    protected function forgetCategoryTreeComputed(): void
    {
        unset($this->categoryTree);
    }

    /**
     * @return array{
     *     paginator: LengthAwarePaginator<int, Category>,
     *     rows: list<array{category: Category, depth: int, children_count: int, is_context: bool}>,
     *     expandableIds: list<int>
     * }
     */
    #[Computed]
    public function categoryTree(): array
    {
        $businessId = Auth::user()?->current_business_id;

        $categories = Category::query()
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, function ($query) use ($businessId): void {
                $query->where(function ($query) use ($businessId): void {
                    $query->where(fn ($query) => $query->system())
                        ->orWhere('business_id', $businessId);
                });
            })
            ->when($this->type !== '', fn ($query) => $query->ofType($this->type))
            ->orderByDesc('is_system')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $filtering = $this->search !== '' || $this->statusFilter !== '';
        $matchIds = null;

        if ($filtering) {
            $search = mb_strtolower(trim($this->search));

            $matchIds = $categories
                ->filter(function (Category $category) use ($search): bool {
                    if ($this->statusFilter === 'active' && ! $category->is_active) {
                        return false;
                    }

                    if ($this->statusFilter === 'inactive' && $category->is_active) {
                        return false;
                    }

                    if ($search === '') {
                        return true;
                    }

                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $category->name,
                        $category->slug,
                        $category->code,
                    ])));

                    return str_contains($haystack, $search);
                })
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->buildCategoryTreePage($categories, $matchIds);
    }

    /**
     * @return LengthAwarePaginator<int, Category>
     */
    #[Computed]
    public function categories(): LengthAwarePaginator
    {
        return $this->categoryTree['paginator'];
    }

    /**
     * @return list<array{category: Category, depth: int, children_count: int, is_context: bool}>
     */
    #[Computed]
    public function rows(): array
    {
        return $this->categoryTree['rows'];
    }

    public function isFiltering(): bool
    {
        return $this->search !== '' || $this->statusFilter !== '';
    }

    public function formatCreatedAt(Category $category): string
    {
        return Jalalian::fromDateTime($category->created_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.categories') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.categories') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.categories') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.categories_page_hint') }}</flux:text>
            </div>

            <flux:button
                variant="primary"
                color="teal"
                icon="plus"
                wire:click="$dispatch('panels.accounting.category.create.assign-data', { parent: null })"
            >
                {{ __('general.create_category') }}
            </flux:button>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />

            @if (! $typeLocked)
                <flux:select wire:model.live="type" searchable variant="listbox" placeholder="{{ __('general.category_type') }}">
                    @foreach (CategoryType::cases() as $categoryType)
                        <flux:select.option value="{{ $categoryType->value }}" wire:key="biz-type-{{ $categoryType->value }}">
                            {{ $categoryType->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="statusFilter" searchable variant="listbox" placeholder="{{ __('general.all_statuses') }}">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('general.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('general.inactive') }}</flux:select.option>
            </flux:select>
        </div>

        @if (! $this->isFiltering() && $this->categoryTree['expandableIds'] !== [])
            <div class="mb-4 flex justify-end gap-2">
                <flux:tooltip content="{{ __('general.expand_all') }}">
                    <flux:button
                        size="xs"
                        variant="ghost"
                        color="zinc"
                        icon="chevrons-up-down"
                        icon:variant="outline"
                        wire:click="expandAllCategories"
                    />
                </flux:tooltip>
                <flux:tooltip content="{{ __('general.collapse_all') }}">
                    <flux:button
                        size="xs"
                        variant="ghost"
                        color="zinc"
                        icon="chevrons-down-up"
                        icon:variant="outline"
                        wire:click="collapseAllCategories"
                    />
                </flux:tooltip>
            </div>
        @endif

        <flux:table :paginate="$this->categories">
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.category_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_active') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->rows as $row)
                    @php($category = $row['category'])
                    <flux:table.row
                        :key="$category->id"
                        data-category-id="{{ $category->id }}"
                        data-depth="{{ $row['depth'] }}"
                        class="{{ $row['is_context'] ? 'opacity-60' : '' }}"
                    >
                        <flux:table.cell>
                            <div
                                class="flex items-center gap-2 {{ $row['depth'] === 0 ? 'font-medium' : '' }}"
                                style="padding-inline-start: {{ $row['depth'] * 1.5 }}rem"
                            >
                                @if ($row['children_count'] > 0 && ! $this->isFiltering())
                                    <flux:tooltip content="{{ __('general.toggle_subcategories') }}">
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            color="zinc"
                                            icon="chevron-down"
                                            icon:variant="outline"
                                            class="{{ in_array($category->id, $expandedIds, true) ? '' : 'ltr:-rotate-90 rtl:rotate-90' }}"
                                            wire:click="toggleExpand({{ $category->id }})"
                                        />
                                    </flux:tooltip>
                                @else
                                    <span class="inline-block size-7 shrink-0"></span>
                                @endif

                                <div class="min-w-0">
                                    <div>{{ $category->name }}</div>
                                    <flux:text size="sm" class="mt-0.5 block text-zinc-500" dir="ltr">{{ $category->slug }}</flux:text>
                                </div>

                                @if ($category->is_system)
                                    <flux:badge size="sm" color="zinc">{{ __('general.system') }}</flux:badge>
                                @endif

                                @if ($row['children_count'] > 0)
                                    <flux:badge size="sm" color="zinc">
                                        {{ __('general.subcategories_count', ['count' => $row['children_count']]) }}
                                    </flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$category->type->badgeColor()">
                                {{ $category->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$category->is_active ? 'green' : 'zinc'">
                                {{ $category->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($category) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @php($editable = ! $category->is_system && (int) $category->business_id === (int) Auth::user()?->current_business_id)
                            @if ($category->is_active || $editable)
                                <div class="flex justify-end gap-2">
                                    @if ($category->is_active)
                                        <flux:tooltip content="{{ __('general.create_subcategory') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="teal"
                                                icon="plus"
                                                icon:variant="outline"
                                                wire:click="$dispatch('panels.accounting.category.create.assign-data', { parent: {{ $category->id }} })"
                                            />
                                        </flux:tooltip>
                                    @endif
                                    @if ($editable)
                                        <flux:tooltip content="{{ __('general.edit') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="blue"
                                                icon="pencil"
                                                icon:variant="outline"
                                                wire:click="$dispatch('panels.accounting.category.edit.assign-data', { category: {{ $category->id }} })"
                                            />
                                        </flux:tooltip>
                                        <flux:tooltip content="{{ __('general.delete') }}">
                                            <flux:button
                                                size="xs"
                                                variant="danger"
                                                icon="trash"
                                                icon:variant="outline"
                                                wire:click="$dispatch('panels.accounting.category.delete.assign-data', { category: {{ $category->id }} })"
                                            />
                                        </flux:tooltip>
                                    @endif
                                </div>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            {{ __('general.no_categories') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.category.create :type="$type" :key="'accounting-category-create-'.$type" />
    <livewire:accounting.category.edit :key="'accounting-category-edit'" />
    <livewire:accounting.category.delete :key="'accounting-category-delete'" />
</div>
