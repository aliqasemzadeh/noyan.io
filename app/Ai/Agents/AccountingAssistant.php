<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateBankAccount;
use App\Ai\Tools\CreateTransaction;
use App\Models\Accounting\Account;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

#[Provider('gap')]
#[MaxSteps(5)]
#[Timeout(120)]
class AccountingAssistant implements Agent, HasTools, RemembersConversationsContract
{
    use Promptable;
    use RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $accounts = $this->existingAccountsBlock();

        return <<<PROMPT
تو یک دستیار هوشمند حسابداری هستی. به فارسی و مختصر پاسخ بده.

{$accounts}

## وظایف و تشخیص قصد
1) پاسخ به استعلام‌ها و سؤالات (مانند موجودی/مانده، تعداد حساب‌ها، اطلاعات حساب‌ها):
- اگر کاربر درباره مانده/موجودی یک یا چند حساب، تعداد حساب‌ها، یا مشخصات آنها پرسید، مستقیماً از بخش «حساب‌های بانکی/موجود این کسب‌وکار» پاسخ بده.
- مانده هر حساب و در صورت نیاز ارز/واحد پول را به زیبایی و با فرمت تفکیک‌شده (سه رقم سه رقم) یا دقیق گزارش کن.

2) ثبت تراکنش (create_transaction) — اولویت با ثبت تراکنش است وقتی پیامک بانکی «برداشت» یا «واریز» دارد:
- متن شامل «برداشت» یا «واریز» است، یا
- کاربر گفته تراکنش ثبت کن، یا
- شماره حساب پیامک در لیست حساب‌های موجود بالاست.
در این حالت:
- فوراً create_transaction را صدا بزن (با account_number از پیامک).
- هرگز create_bank_account را صدا نزن.
- هرگز نام بانک یا نام نمایشی حساب را نپرس.
- برداشت = expense، واریز = income.
- مبلغ = مبلغ برداشت/واریز (نه مانده).
- تاریخ را از پیامک بگیر؛ اگر نبود امروز.
- اگر شماره حساب در لیست نبود، فقط بپرس کدام حساب موجود است؛ حساب جدید نساز.

3) ساخت حساب بانکی (create_bank_account) — فقط وقتی:
- کاربر صریحاً ساخت حساب خواسته («حساب بساز»، «حساب کن») و
- پیامک «برداشت/واریز» برای ثبت تراکنش نیست، و
- شماره حساب هنوز در لیست موجود نیست.
در این حالت:
- name و bank_name الزامی‌اند؛ اگر نبودند در یک سؤال کوتاه بپرس.
- هرگز از روی شماره حساب حدس نزن.
- مبلغ برداشت/واریز را نادیده بگیر؛ تراکنش نساز.
- مانده = opening/current balance.

خروجی فارسی ابزار را عیناً به کاربر بده. اگر هیچ ابزاری نیاز نبود، مختصر و دقیق به فارسی پاسخ بده.
PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return [
            new CreateBankAccount,
            new CreateTransaction,
        ];
    }

    protected function existingAccountsBlock(): string
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return 'حساب‌های موجود: هیچ (کسب‌وکار فعال نیست).';
        }

        $accounts = Account::query()
            ->with('currency')
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'account_number', 'bank_name', 'current_balance', 'currency_id']);

        if ($accounts->isEmpty()) {
            return 'حساب‌های موجود: هیچ.';
        }

        $lines = $accounts->map(function (Account $account): string {
            $number = $account->account_number ?: '—';
            $bank = $account->bank_name ?: '—';
            $currency = $account->currency?->name ?: ($account->currency?->code ?: '');
            $balance = number_format((float) ($account->current_balance ?? 0), 0, '.', ',');
            $balanceStr = $currency !== '' ? "{$balance} {$currency}" : $balance;

            return "- {$account->name} | بانک: {$bank} | شماره: {$number} | مانده/موجودی: {$balanceStr}";
        })->implode("\n");

        return "حساب‌های بانکی/موجود این کسب‌وکار (هم برای پاسخ به استعلام مانده/مشخصات حساب و هم فقط از همین‌ها برای ثبت تراکنش استفاده کن):\n{$lines}";
    }
}
