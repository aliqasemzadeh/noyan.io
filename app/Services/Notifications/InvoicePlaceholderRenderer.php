<?php

namespace App\Services\Notifications;

use App\Models\Accounting\Invoice;
use Morilog\Jalali\Jalalian;

class InvoicePlaceholderRenderer
{
    /**
     * @param  array<string, string|null>  $extra
     */
    public function render(string $template, Invoice $invoice, array $extra = []): string
    {
        $replacements = array_merge($this->placeholders($invoice), $extra);

        $search = [];
        $replace = [];

        foreach ($replacements as $key => $value) {
            $search[] = '{'.$key.'}';
            $replace[] = (string) ($value ?? '');
        }

        return str_replace($search, $replace, $template);
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(Invoice $invoice): array
    {
        $invoice->loadMissing(['party', 'business']);

        $balance = bcsub((string) $invoice->total_amount, (string) $invoice->paid_amount, 2);

        return [
            'client_name' => $invoice->displayPartyName(),
            'company_name' => (string) ($invoice->business?->name ?? ''),
            'business_name' => (string) ($invoice->business?->name ?? ''),
            'invoice_number' => (string) $invoice->invoice_number,
            'amount' => $this->formatAmount((string) $invoice->total_amount),
            'balance' => $this->formatAmount($balance),
            'due_date' => $this->formatDate($invoice->due_date),
            'link' => '',
        ];
    }

    protected function formatAmount(string $amount): string
    {
        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized);
    }

    protected function formatDate(mixed $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Jalalian::fromDateTime($date)->format('Y/m/d');
    }
}
