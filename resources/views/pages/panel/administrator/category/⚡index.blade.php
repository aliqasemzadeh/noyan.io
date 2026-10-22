<?php

use App\Enums\CategoryType;
use App\Livewire\Concerns\WithCategoryTreeListing;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
    public string $typeFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->forgetCategoryTreeComputed();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
        $this->forgetCategoryTreeComputed();
    }

    #[On('panels.administrator.category.index.table')]
    public function refreshTable(): void
    {
        $this->forgetCategoryTreeComputed();
    }

    protected function forgetCategoryTreeComputed(): void
    {
        unset($this->categoryTree, $this->categories, $this->rows);
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
        $categories = Category::query()
            ->system()
            ->when($this->typeFilter !== '', fn ($query) => $query->ofType($this->typeFilter))
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $matchIds = null;

        if ($this->search !== '') {
            $search = mb_strtolower(trim($this->search));

            $matchIds = $categories
                ->filter(function (Category $category) use ($search): bool {
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
        return $this->search !== '';
    }

    public function formatCreatedAt(Category $category): string
    {
        return Jalalian::fromDateTime($category->created_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.system_categories') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('system.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_categories') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.system_categories') }}
            </flux:heading>

            <flux:modal.trigger name="category.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_category') }}
                </flux:button>
            </flux:modal.trigger>
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

            <flux:select wire:model.live="typeFilter" searchable variant="listbox" placeholder="{{ __('general.all_types') }}">
                <flux:select.option value="">{{ __('general.all_types') }}</flux:select.option>
                @foreach (CategoryType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="admin-type-filter-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
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
                <flux:table.column>{{ __('general.category_code') }}</flux:table.column>
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
                            <span dir="ltr">{{ $category->code }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$category->is_active ? 'green' : 'zinc'">
                                {{ $category->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($category) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.administrator.category.edit.assign-data', { category: {{ $category->id }} })"
                                    />
                                </flux:tooltip>

                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.administrator.category.delete.assign-data', { category: {{ $category->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            {{ __('general.no_categories') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:category.create :key="'category-create'" />
    <livewire:category.edit :key="'category-edit'" />
    <livewire:category.delete :key="'category-delete'" />
</div>
