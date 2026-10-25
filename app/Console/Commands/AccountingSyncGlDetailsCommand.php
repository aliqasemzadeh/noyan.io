<?php

namespace App\Console\Commands;

use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Party;
use App\Models\Accounting\Project;
use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountingSyncGlDetailsCommand extends Command
{
    protected $signature = 'accounting:sync-gl-details
                            {business? : Business ID}
                            {--all : Sync floating details for every business}';

    protected $description = 'Upsert gl_details rows for parties, cash/bank accounts, cost centers, and projects';

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
            $counts = [
                'party' => $this->syncModel(Party::query()->where('business_id', $business->id)),
                'account' => $this->syncModel(Account::query()->where('business_id', $business->id)),
                'cost_center' => $this->syncModel(CostCenter::query()->where('business_id', $business->id)),
                'project' => $this->syncModel(Project::query()->where('business_id', $business->id)),
            ];

            $this->info(sprintf(
                'Business #%d: party=%d account=%d cost_center=%d project=%d',
                $business->id,
                $counts['party'],
                $counts['account'],
                $counts['cost_center'],
                $counts['project'],
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    protected function syncModel(Builder $query): int
    {
        $count = 0;

        $query->orderBy('id')->each(function (Model $model) use (&$count): void {
            if (! method_exists($model, 'syncFloatingDetail')) {
                return;
            }

            $model->syncFloatingDetail();
            $count++;
        });

        return $count;
    }
}
