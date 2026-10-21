<?php

namespace App\Enums\Accounting;

enum InvoiceNotificationType: string
{
    case NewInvoice = 'new_invoice';
    case PaymentReminder = 'payment_reminder';
    case ReminderBefore = 'reminder_before';
    case ReminderDue = 'reminder_due';
    case ReminderAfter = 'reminder_after';
    case PaymentReceipt = 'payment_receipt';

    public function templateKey(): string
    {
        return match ($this) {
            self::NewInvoice => 'new_invoice',
            self::PaymentReminder, self::ReminderBefore, self::ReminderDue, self::ReminderAfter => 'payment_reminder',
            self::PaymentReceipt => 'payment_receipt',
        };
    }

    public function isIdempotent(): bool
    {
        return match ($this) {
            self::PaymentReceipt => false,
            default => true,
        };
    }
}
