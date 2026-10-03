<?php

namespace App\Actions\Cheques;

use App\Enums\Accounting\ChequeStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class UpdateChequeAction
{
    /**
     * @param  array{
     *     cheque_number?: string,
     *     sayad_number?: string|null,
     *     bank_name?: string,
     *     bank_branch?: string|null,
     *     due_date?: string,
     *     account_id?: int|null,
     *     invoice_id?: int|null,
     *     note?: string|null
     * }  $data
     */
    public function handle(User $user, Cheque $cheque, array $data): Cheque
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to update a cheque.');
        }

        return DB::transaction(function () use ($cheque, $data, $businessId): Cheque {
            $locked = Cheque::query()
                ->where('business_id', $businessId)
                ->whereKey($cheque->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'cheque' => [__('general.cheque_not_found')],
                ]);
            }

            if (! in_array($locked->status, [ChequeStatus::Registered, ChequeStatus::Deposited], true)) {
                throw ValidationException::withMessages([
                    'cheque' => [__('general.cheque_cannot_edit')],
                ]);
            }

            $chequeNumber = array_key_exists('cheque_number', $data)
                ? trim((string) $data['cheque_number'])
                : $locked->cheque_number;
            $bankName = array_key_exists('bank_name', $data)
                ? trim((string) $data['bank_name'])
                : $locked->bank_name;

            $duplicateExists = Cheque::query()
                ->where('business_id', $businessId)
                ->where('type', $locked->type->value)
                ->where('bank_name', $bankName)
                ->where('cheque_number', $chequeNumber)
                ->whereKeyNot($locked->id)
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'cheque_number' => [__('general.cheque_number_duplicate')],
                ]);
            }

            if (array_key_exists('due_date', $data) && strcmp((string) $data['due_date'], $locked->issue_date->toDateString()) < 0) {
                throw ValidationException::withMessages([
                    'due_date' => [__('general.cheque_due_before_issue')],
                ]);
            }

            $accountId = array_key_exists('account_id', $data) ? $data['account_id'] : $locked->account_id;

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

            $invoiceId = array_key_exists('invoice_id', $data) ? $data['invoice_id'] : $locked->invoice_id;

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

            $locked->forceFill([
                'cheque_number' => $chequeNumber,
                'sayad_number' => array_key_exists('sayad_number', $data) ? $data['sayad_number'] : $locked->sayad_number,
                'bank_name' => $bankName,
                'bank_branch' => array_key_exists('bank_branch', $data) ? $data['bank_branch'] : $locked->bank_branch,
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $locked->due_date,
                'account_id' => $accountId,
                'invoice_id' => $invoiceId,
                'note' => array_key_exists('note', $data) ? $data['note'] : $locked->note,
            ])->save();

            Cheque::forgetAlertsCache($businessId);

            return $locked->fresh(['party', 'account', 'invoice']);
        });
    }
}
