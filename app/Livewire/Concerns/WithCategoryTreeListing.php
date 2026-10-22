<?php

namespace App\Livewire\Concerns;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

trait WithCategoryTreeListing
{
    /** @var list<int> */
    public array $expandedIds = [];

    public function toggleExpand(int $categoryId): void
    {
        if (in_array($categoryId, $this->expandedIds, true)) {
            $this->expandedIds = array_values(array_filter(
                $this->expandedIds,
                fn (int $id): bool => $id !== $categoryId,
            ));
        } else {
            $this->expandedIds[] = $categoryId;
        }

        $this->forgetCategoryTreeComputed();
    }

    public function expandAllCategories(): void
    {
        $this->expandedIds = $this->categoryTree['expandableIds'];
        $this->forgetCategoryTreeComputed();
    }

    public function collapseAllCategories(): void
    {
        $this->expandedIds = [];
        $this->forgetCategoryTreeComputed();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  list<int>|null  $matchIds  null = show full tree; list = filter mode with ancestors
     * @return array{
     *     paginator: LengthAwarePaginator<int, Category>,
     *     rows: list<array{category: Category, depth: int, children_count: int, is_context: bool}>,
     *     expandableIds: list<int>
     * }
     */
    protected function buildCategoryTreePage(Collection $categories, ?array $matchIds = null): array
    {
        $filtering = $matchIds !== null;

        if ($filtering) {
            $visibleIds = Category::idsWithAncestors($categories->keyBy('id'), $matchIds);
            $visibleLookup = array_fill_keys($visibleIds, true);
            $categories = $categories
                ->filter(fn (Category $category): bool => isset($visibleLookup[$category->id]))
                ->values();
        }

        $forest = Category::nestForest($categories);
        $expandableIds = Category::expandableIdsFromForest($forest);

        $page = Paginator::resolveCurrentPage();
        $perPage = 15;
        $pageRoots = $forest->forPage($page, $perPage)->values();

        /** @var LengthAwarePaginator<int, Category> $paginator */
        $paginator = new Paginator(
            $pageRoots,
            $forest->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );

        $rows = Category::flattenForestForListing(
            $pageRoots,
            $this->expandedIds,
            expandAll: $filtering,
            matchedIds: $matchIds ?? [],
        );

        return [
            'paginator' => $paginator,
            'rows' => $rows,
            'expandableIds' => $expandableIds,
        ];
    }

    abstract protected function forgetCategoryTreeComputed(): void;
}
