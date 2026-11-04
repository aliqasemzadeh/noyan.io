<?php

namespace App\Exports;

use App\Models\Accounting\Transaction;
use App\Support\LocaleDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<Transaction>
 */
class TransactionsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Transaction>  $query
     */
    public function __construct(
        protected Builder $query,
    ) {}

    public function collection(): Collection
    {
        return $this->query
            ->with(['account', 'destinationAccount', 'party', 'category:id,name'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            __('general.transaction_date'),
            __('general.transaction_type'),
            __('general.amount'),
            __('general.account'),
            __('general.destination_account'),
            __('general.party'),
            __('general.category'),
            __('general.reference_number'),
            __('general.note'),
            __('general.balance_after'),
        ];
    }

    /**
     * @param  Transaction  $transaction
     * @return list<string|null>
     */
    public function map($transaction): array
    {
        return [
            LocaleDate::formatDateTime($transaction->transaction_date),
            $transaction->type?->label() ?? (string) $transaction->type,
            (string) $transaction->amount,
            $transaction->account?->name,
            $transaction->destinationAccount?->name,
            $transaction->party?->name,
            $transaction->category?->name,
            $transaction->reference_number,
            $transaction->note,
            $transaction->balance_after !== null ? (string) $transaction->balance_after : null,
        ];
    }
}
