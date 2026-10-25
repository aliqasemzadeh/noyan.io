<?php

namespace App\Console\Commands;

use App\Enums\CategoryType;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Console\Command;

class AccountingMigrateCategoriesToGlCommand extends Command
{
    protected $signature = 'accounting:migrate-categories-to-gl
                            {business? : Business ID}
                            {--all : Run for every business}';

    protected $description = 'Link accounting categories to matching GL subsidiary accounts by system key / code heuristics';

    public function handle(): int
    {
        $businesses = $this->option('all')
            ? Business::query()->orderBy('id')->get()
            : collect([Business::query()->find($this->argument('business'))])->filter();

        if ($businesses->isEmpty()) {
            $this->error('Provide a business ID or use --all.');

            return self::FAILURE;
        }

        $map = [
            'sales' => 'sales',
            'services' => 'services',
            'receivable' => 'accounts_receivable',
            'payable' => 'accounts_payable',
            'cash_bank' => 'cash_and_bank',
            'inventory' => 'inventory',
            'tax_payable' => 'vat_payable',
            'fx_gain' => 'fx_gain',
            'rent' => 'rent_expense',
            'salary' => 'payroll_expense',
            'bank_fee' => 'bank_fees',
        ];

        foreach ($businesses as $business) {
            $linked = 0;

            $categories = Category::query()
                ->where(function ($query) use ($business): void {
                    $query->whereNull('business_id')
                        ->orWhere('business_id', $business->id);
                })
                ->whereIn('type', CategoryType::accountingCases())
                ->whereNull('gl_account_id')
                ->get();

            foreach ($categories as $category) {
                $systemKey = $map[$category->code] ?? null;

                if ($systemKey === null) {
                    continue;
                }

                $glAccount = GlAccount::findBySystemKey($business, $systemKey);

                if ($glAccount === null) {
                    continue;
                }

                // Only attach business-owned category rows; system categories stay shared.
                if ($category->business_id === null) {
                    continue;
                }

                $category->forceFill(['gl_account_id' => $glAccount->id])->save();
                $linked++;
            }

            $this->info("Business #{$business->id}: linked {$linked} categories.");
        }

        return self::SUCCESS;
    }
}
