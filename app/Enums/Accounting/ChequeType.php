<?php

namespace App\Enums\Accounting;

enum ChequeType: string
{
    case Received = 'received';
    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Received => __('general.cheque_type_received'),
            self::Issued => __('general.cheque_type_issued'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Received => 'teal',
            self::Issued => 'amber',
        };
    }

    public function transactionType(): TransactionType
    {
        return match ($this) {
            self::Received => TransactionType::Income,
            self::Issued => TransactionType::Expense,
        };
    }

    /**
     * Apply register-time party balance effect and return the new balance.
     */
    public function applyRegisterPartyBalance(string $currentBalance, string $amount): string
    {
        return match ($this) {
            self::Received => bcsub($currentBalance, $amount, 18),
            self::Issued => bcadd($currentBalance, $amount, 18),
        };
    }

    /**
     * Reverse register-time party balance effect and return the new balance.
     */
    public function applyReversePartyBalance(string $currentBalance, string $amount): string
    {
        return match ($this) {
            self::Received => bcadd($currentBalance, $amount, 18),
            self::Issued => bcsub($currentBalance, $amount, 18),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
