<?php

namespace App\Enums\Business;

enum BusinessPermission: string
{
    case QuoteView = 'quote_view';
    case QuoteCreate = 'quote_create';
    case QuoteEdit = 'quote_edit';
    case QuoteDelete = 'quote_delete';

    case InvoiceView = 'invoice_view';
    case InvoiceCreate = 'invoice_create';
    case InvoiceEdit = 'invoice_edit';
    case InvoiceEditFinalized = 'invoice_edit_finalized';
    case InvoiceDelete = 'invoice_delete';

    case PartyView = 'party_view';
    case PartyCreate = 'party_create';
    case PartyEdit = 'party_edit';
    case PartyDelete = 'party_delete';

    case AccountView = 'account_view';
    case AccountCreate = 'account_create';
    case AccountEdit = 'account_edit';
    case AccountDelete = 'account_delete';

    case TransactionView = 'transaction_view';
    case TransactionCreate = 'transaction_create';
    case TransactionEdit = 'transaction_edit';
    case TransactionDelete = 'transaction_delete';

    case JournalEntryView = 'journal_entry_view';
    case JournalEntryCreate = 'journal_entry_create';
    case JournalEntryEdit = 'journal_entry_edit';
    case JournalEntryDelete = 'journal_entry_delete';

    case GlAccountView = 'gl_account_view';
    case GlAccountCreate = 'gl_account_create';
    case GlAccountEdit = 'gl_account_edit';
    case GlAccountDelete = 'gl_account_delete';

    case LoanView = 'loan_view';
    case LoanCreate = 'loan_create';
    case LoanEdit = 'loan_edit';
    case LoanDelete = 'loan_delete';

    case ChequeView = 'cheque_view';
    case ChequeCreate = 'cheque_create';
    case ChequeEdit = 'cheque_edit';
    case ChequeDelete = 'cheque_delete';

    case FiscalYearView = 'fiscal_year_view';
    case FiscalYearCreate = 'fiscal_year_create';
    case FiscalYearEdit = 'fiscal_year_edit';
    case FiscalYearDelete = 'fiscal_year_delete';

    case CostCenterView = 'cost_center_view';
    case CostCenterCreate = 'cost_center_create';
    case CostCenterEdit = 'cost_center_edit';
    case CostCenterDelete = 'cost_center_delete';

    case ProjectView = 'project_view';
    case ProjectCreate = 'project_create';
    case ProjectEdit = 'project_edit';
    case ProjectDelete = 'project_delete';

    case CurrencyView = 'currency_view';
    case CurrencyCreate = 'currency_create';
    case CurrencyEdit = 'currency_edit';
    case CurrencyDelete = 'currency_delete';

    case CategoryView = 'category_view';
    case CategoryCreate = 'category_create';
    case CategoryEdit = 'category_edit';
    case CategoryDelete = 'category_delete';

    case ProductView = 'product_view';
    case ProductCreate = 'product_create';
    case ProductEdit = 'product_edit';
    case ProductDelete = 'product_delete';

    case BrandView = 'brand_view';
    case BrandCreate = 'brand_create';
    case BrandEdit = 'brand_edit';
    case BrandDelete = 'brand_delete';

    case WarehouseView = 'warehouse_view';
    case WarehouseCreate = 'warehouse_create';
    case WarehouseEdit = 'warehouse_edit';
    case WarehouseDelete = 'warehouse_delete';

    case StockReceive = 'stock_receive';
    case StockIssue = 'stock_issue';
    case StockTransfer = 'stock_transfer';
    case StockAdjust = 'stock_adjust';
    case StockAllocate = 'stock_allocate';
    case StockConvert = 'stock_convert';
    case StockMovementView = 'stock_movement_view';

    public function label(): string
    {
        return __('general.business_permission_'.$this->value);
    }

    public function group(): string
    {
        return match ($this) {
            self::QuoteView, self::QuoteCreate, self::QuoteEdit, self::QuoteDelete => 'quote',
            self::InvoiceView, self::InvoiceCreate, self::InvoiceEdit, self::InvoiceEditFinalized, self::InvoiceDelete => 'invoice',
            self::PartyView, self::PartyCreate, self::PartyEdit, self::PartyDelete => 'party',
            self::AccountView, self::AccountCreate, self::AccountEdit, self::AccountDelete => 'account',
            self::TransactionView, self::TransactionCreate, self::TransactionEdit, self::TransactionDelete => 'transaction',
            self::JournalEntryView, self::JournalEntryCreate, self::JournalEntryEdit, self::JournalEntryDelete => 'journal_entry',
            self::GlAccountView, self::GlAccountCreate, self::GlAccountEdit, self::GlAccountDelete => 'gl_account',
            self::LoanView, self::LoanCreate, self::LoanEdit, self::LoanDelete => 'loan',
            self::ChequeView, self::ChequeCreate, self::ChequeEdit, self::ChequeDelete => 'cheque',
            self::FiscalYearView, self::FiscalYearCreate, self::FiscalYearEdit, self::FiscalYearDelete => 'fiscal_year',
            self::CostCenterView, self::CostCenterCreate, self::CostCenterEdit, self::CostCenterDelete => 'cost_center',
            self::ProjectView, self::ProjectCreate, self::ProjectEdit, self::ProjectDelete => 'project',
            self::CurrencyView, self::CurrencyCreate, self::CurrencyEdit, self::CurrencyDelete => 'currency',
            self::CategoryView, self::CategoryCreate, self::CategoryEdit, self::CategoryDelete => 'category',
            self::ProductView, self::ProductCreate, self::ProductEdit, self::ProductDelete => 'product',
            self::BrandView, self::BrandCreate, self::BrandEdit, self::BrandDelete => 'brand',
            self::WarehouseView, self::WarehouseCreate, self::WarehouseEdit, self::WarehouseDelete => 'warehouse',
            self::StockReceive, self::StockIssue, self::StockTransfer, self::StockAdjust, self::StockAllocate, self::StockConvert, self::StockMovementView => 'stock',
        };
    }

    public function isDelete(): bool
    {
        return str_ends_with($this->value, '_delete');
    }

    public function isSaleRelated(): bool
    {
        return in_array($this->group(), ['quote', 'invoice', 'party', 'product'], true);
    }

    public function isAccountingRelated(): bool
    {
        if ($this->isWarehouseRelated()) {
            return false;
        }

        return ! $this->isSaleRelated() || in_array($this->group(), ['party', 'product'], true);
    }

    public function isWarehouseRelated(): bool
    {
        return in_array($this->group(), ['warehouse', 'stock'], true);
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function deleteValues(): array
    {
        return array_values(array_map(
            fn (self $permission): string => $permission->value,
            array_filter(self::cases(), fn (self $permission): bool => $permission->isDelete()),
        ));
    }

    /**
     * @return list<string>
     */
    public static function withoutDeletes(): array
    {
        return array_values(array_map(
            fn (self $permission): string => $permission->value,
            array_filter(self::cases(), fn (self $permission): bool => ! $permission->isDelete()),
        ));
    }

    /**
     * @param  list<self>  $permissions
     * @return list<string>
     */
    public static function valuesOf(array $permissions): array
    {
        return array_values(array_map(
            fn (self $permission): string => $permission->value,
            $permissions,
        ));
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }

    /**
     * @param  list<self>  $permissions
     * @return array<string, list<self>>
     */
    public static function groupedFrom(array $permissions): array
    {
        $groups = [];

        foreach ($permissions as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }
}
