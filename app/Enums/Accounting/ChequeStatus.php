<?php

namespace App\Enums\Accounting;

enum ChequeStatus: string
{
    case Registered = 'registered';
    case Deposited = 'deposited';
    case Cleared = 'cleared';
    case Bounced = 'bounced';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Registered => __('general.cheque_status_registered'),
            self::Deposited => __('general.cheque_status_deposited'),
            self::Cleared => __('general.cheque_status_cleared'),
            self::Bounced => __('general.cheque_status_bounced'),
            self::Returned => __('general.cheque_status_returned'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Registered => 'amber',
            self::Deposited => 'sky',
            self::Cleared => 'green',
            self::Bounced => 'rose',
            self::Returned => 'zinc',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Cleared, self::Bounced, self::Returned => true,
            self::Registered, self::Deposited => false,
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Registered, self::Deposited => true,
            self::Cleared, self::Bounced, self::Returned => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function forType(ChequeType $type): array
    {
        return match ($type) {
            ChequeType::Received => [
                self::Registered,
                self::Deposited,
                self::Cleared,
                self::Bounced,
                self::Returned,
            ],
            ChequeType::Issued => [
                self::Registered,
                self::Cleared,
                self::Bounced,
                self::Returned,
            ],
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(ChequeType $type): array
    {
        return match ($type) {
            ChequeType::Received => match ($this) {
                self::Registered => [self::Deposited, self::Cleared, self::Bounced, self::Returned],
                self::Deposited => [self::Cleared, self::Bounced],
                self::Cleared, self::Bounced, self::Returned => [],
            },
            ChequeType::Issued => match ($this) {
                self::Registered => [self::Cleared, self::Bounced, self::Returned],
                self::Deposited, self::Cleared, self::Bounced, self::Returned => [],
            },
        };
    }

    public function canTransitionTo(self $to, ChequeType $type): bool
    {
        return in_array($to, $this->allowedTransitions($type), true);
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
    public static function openValues(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen())),
        );
    }
}
