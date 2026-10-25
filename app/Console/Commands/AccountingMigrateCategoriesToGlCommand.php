<?php

namespace App\Console\Commands;

use App\Enums\CategoryType;
use App\Models\Business;
use App\Models\Category;
use App\Support\Accounting\CategoryGlAccountMap;
use Illuminate\Console\Command;

class AccountingMigrateCategoriesToGlCommand extends Command
{
    protected $signature = 'accounting:migrate-categories-to-gl
                            {business? : Business ID}
                            {--all : Run for every business}';

    protected $description = 'Link accounting categories to matching GL subsidiary accounts (business rows + code heuristics)';

    public function handle(): int
    {
        $businesses = $this->option('all')
            ? Business::query()->orderBy('id')->get()
            : collect([Business::query()->find($this->argument('business'))])->filter();

        if ($businesses->isEmpty()) {
            $this->error('Provide a business ID or use --all.');

            return self::FAILURE;
        }

        foreach ($businesses as $business) {
            $linked = 0;
            $resolvedRuntime = 0;

            $categories = Category::query()
                ->where(function ($query) use ($business): void {
                    $query->whereNull('business_id')
                        ->orWhere('business_id', $business->id);
                })
                ->whereIn('type', CategoryType::accountingCases())
                ->get();

            foreach ($categories as $category) {
                $glAccount = CategoryGlAccountMap::resolve($business, $category);

                if ($glAccount === null) {
                    continue;
                }

                // Persist FK only on business-owned rows (system categories are shared).
                if ($category->business_id !== null) {
                    if ((int) $category->gl_account_id !== (int) $glAccount->id) {
                        $category->forceFill(['gl_account_id' => $glAccount->id])->save();
                        $linked++;
                    }

                    continue;
                }

                // System / shared categories resolve at runtime via CategoryGlAccountMap.
                if ($category->gl_account_id === null) {
                    $resolvedRuntime++;
                }
            }

            $this->info("Business #{$business->id}: linked {$linked} business categories; {$resolvedRuntime} system categories resolve via code map.");
        }

        return self::SUCCESS;
    }
}
