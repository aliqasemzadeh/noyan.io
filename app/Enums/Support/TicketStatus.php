<?php

namespace App\Enums\Support;

enum TicketStatus: string
{
    case New = 'new';
    case WaitingStaff = 'waiting_staff';
    case WaitingUser = 'waiting_user';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => __('general.ticket_status_new'),
            self::WaitingStaff => __('general.ticket_status_waiting_staff'),
            self::WaitingUser => __('general.ticket_status_waiting_user'),
            self::Closed => __('general.ticket_status_closed'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::New => 'sky',
            self::WaitingStaff => 'amber',
            self::WaitingUser => 'violet',
            self::Closed => 'zinc',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isOpen(): bool
    {
        return ! $this->isClosed();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
