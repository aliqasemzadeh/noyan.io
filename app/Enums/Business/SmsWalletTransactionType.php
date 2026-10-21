<?php

namespace App\Enums\Business;

enum SmsWalletTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
