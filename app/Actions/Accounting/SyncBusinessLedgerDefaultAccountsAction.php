<?php

namespace App\Actions\Accounting;

use App\Models\Accounting\GlAccount;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Services\Accounting\BusinessLedgerSettingsService;

class SyncBusinessLedgerDefaultAccountsAction
{
    /**
     * @var array<string, string>
     */
    public const SYSTEM_KEY_MAP = [
        'default_accounts_receivable_id' => 'accounts_receivable',
        'default_accounts_payable_id' => 'accounts_payable',
        'default_sales_id' => 'sales',
        'default_sales_discount_id' => 'sales_discount',
        'default_vat_payable_id' => 'vat_payable',
        'default_vat_receivable_id' => 'vat_receivable',
        'default_inventory_id' => 'inventory',
        'default_cogs_id' => 'cogs',
        'default_cash_and_bank_id' => 'cash_and_bank',
        'default_fx_gain_id' => 'fx_gain',
        'default_fx_loss_id' => 'fx_loss',
    ];

    public function __construct(
        private BusinessLedgerSettingsService $settingsService,
    ) {}

    /**
     * Map empty (or all, when overwrite=true) ledger default account FKs from COA system keys.
     * Does not create GL accounts — only fills settings rows.
     */
    public function handle(Business $business, bool $overwrite = false): BusinessLedgerSetting
    {
        $settings = BusinessLedgerSetting::forBusiness($business);
        $payload = [];

        foreach (self::SYSTEM_KEY_MAP as $column => $systemKey) {
            if (! $overwrite && filled($settings->{$column})) {
                continue;
            }

            $account = GlAccount::findBySystemKey($business, $systemKey);

            if ($account !== null) {
                $payload[$column] = $account->id;
            }
        }

        if ($payload !== []) {
            $settings->fill($payload)->save();
            $this->settingsService->forget($business->id);
        }

        return $settings->fresh() ?? $settings;
    }
}
