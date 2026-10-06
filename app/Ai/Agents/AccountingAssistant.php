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

## تشخیص قصد (الزامی)
اولویت با ثبت تراکنش است وقتی پیامک بانکی «برداشت» یا «واریز» دارد.

1) ثبت تراکنش (create_transaction) — اگر هر کدام درست بود:
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

2) ساخت حساب بانکی (create_bank_account) — فقط وقتی:
- کاربر صریحاً ساخت حساب خواسته («حساب بساز»، «حساب کن») و
- پیامک «برداشت/واریز» برای ثبت تراکنش نیست، و
- شماره حساب هنوز در لیست موجود نیست.
در این حالت:
- name و bank_name الزامی‌اند؛ اگر نبودند در یک سؤال کوتاه بپرس.
- هرگز از روی شماره حساب حدس نزن.
- مبلغ برداشت/واریز را نادیده بگیر؛ تراکنش نساز.
- مانده = opening/current balance.

خروجی فارسی ابزار را عیناً به کاربر بده. اگر هیچ‌کدام نبود، مختصر پاسخ بده.
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
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'account_number', 'bank_name']);

        if ($accounts->isEmpty()) {
            return 'حساب‌های موجود: هیچ.';
        }

        $lines = $accounts->map(function (Account $account): string {
            $number = $account->account_number ?: '—';
            $bank = $account->bank_name ?: '—';

            return "- {$account->name} | بانک: {$bank} | شماره: {$number}";
        })->implode("\n");

        return "حساب‌های بانکی/موجود این کسب‌وکار (فقط از همین‌ها برای تراکنش استفاده کن):\n{$lines}";
    }
}
