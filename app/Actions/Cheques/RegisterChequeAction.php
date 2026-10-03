<?php

namespace App\Actions\Cheques;

use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RegisterChequeAction
{
    /**
     * @param  array{
     *     type: string|ChequeType,
     *     party_id: int,
     *     account_id?: int|null,
     *     invoice_id?: int|null,
     *     cheque_number: string,
     *     sayad_number?: string|null,
     *     bank_name: string,
     *     bank_branch?: string|null,
     *     amount: string|float|int,
     *     issue_date: string,
     *     due_date: string,
     *     note?: string|null
     * }  $data
     */
    public function handle(User $user, array $data): Cheque
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to register a cheque.');
        }

        return DB::transaction(function () use ($user, $data, $businessId): Cheque {
            $type = $data['type'] instanceof ChequeType
                ? $data['type']
                : ChequeType::from((string) $data['type']);

            $amount = $this->normalizeDecimal((string) $data['amount']);

            if (bccomp($amount, '0', 18) !== 1) {
                throw ValidationException::withMessages([
                    'amount' => [__('general.cheque_amount_must_be_positive')],
                ]);
            }

            if (strcmp((string) $data['due_date'], (string) $data['issue_date']) < 0) {
                throw ValidationException::withMessages([
                    'due_date' => [__('general.cheque_due_before_issue')],
                ]);
            }

            $party = Party::query()
                ->where('business_id', $businessId)
                ->whereKey($data['party_id'])
                ->lockForUpdate()
                ->first();

            if ($party === null) {
                throw ValidationException::withMessages([
                    'party_id' => [__('general.cheque_party_invalid')],
                ]);
            }

            $accountId = $data['account_id'] ?? null;
            $account = null;

            if ($accountId !== null) {
                $account = Account::query()
                    ->where('business_id', $businessId)
                    ->whereKey($accountId)
                    ->first();

                if ($account === null) {
                    throw ValidationException::withMessages([
                        'account_id' => [__('general.cheque_account_invalid')],
                    ]);
                }
            }

            $invoiceId = $data['invoice_id'] ?? null;

            if ($invoiceId !== null) {
                $invoice = Invoice::query()
                    ->where('business_id', $businessId)
                    ->whereKey($invoiceId)
                    ->first();

                if ($invoice === null) {
                    throw ValidationException::withMessages([
                        'invoice_id' => [__('general.cheque_invoice_invalid')],
                    ]);
                }
            }

            $chequeNumber = trim((string) $data['cheque_number']);
            $bankName = trim((string) $data['bank_name']);

            $duplicateExists = Cheque::query()
                ->where('business_id', $businessId)
                ->where('type', $type->value)
                ->where('bank_name', $bankName)
                ->where('cheque_number', $chequeNumber)
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'cheque_number' => [__('general.cheque_number_duplicate')],
                ]);
            }

            $cheque = Cheque::query()->create([
                'business_id' => $businessId,
                'party_id' => $party->id,
                'invoice_id' => $invoiceId,
                'account_id' => $account?->id,
                'created_by' => $user->id,
                'type' => $type,
                'status' => ChequeStatus::Registered,
                'cheque_number' => $chequeNumber,
                'sayad_number' => $data['sayad_number'] ?? null,
                'bank_name' => $bankName,
                'bank_branch' => $data['bank_branch'] ?? null,
                'amount' => $amount,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status_changed_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            $party->forceFill([
                'balance' => $type->applyRegisterPartyBalance((string) $party->balance, $amount),
            ])->save();

            Party::forgetBalanceCache($businessId, (int) $party->id);
            Party::forgetOptionsCache($businessId);
            Cheque::forgetAlertsCache($businessId);

            return $cheque->fresh(['party', 'account', 'invoice']);
        });
    }

    protected function normalizeDecimal(string $amount): string
    {
        $amount = trim($amount);

        if ($amount === '' || ! is_numeric($amount)) {
            return '0';
        }

        if (! str_contains($amount, '.')) {
            return $amount;
        }

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }
}
