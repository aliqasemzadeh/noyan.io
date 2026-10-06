<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateBankAccount;
use App\Ai\Tools\CreateTransaction;
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
        return <<<'PROMPT'
تو یک دستیار هوشمند حسابداری هستی. به فارسی و مختصر پاسخ بده.

اول از متن کاربر تشخیص بده قصدش «ساخت حساب بانکی» است یا «ثبت تراکنش»:
- ساخت حساب: عباراتی مثل «حساب بساز»، «حساب کن»، «این پیامک را حساب کن»، تمرکز روی مانده و تعریف حساب جدید.
- ثبت تراکنش: عباراتی مثل «تراکنش ثبت کن»، «این پیامک تراکنش است»، «برداشت/واریز را ثبت کن»، یا وقتی کاربر صریحاً می‌خواهد مبلغ پیامک روی حساب موجود ثبت شود.

اگر قصد ساخت حساب بانکی است (ابزار create_bank_account):
- هدف ساخت حساب بانکی است، نه ثبت تراکنش.
- شماره حساب = ارقام بعد از «حساب».
- مانده = مبلغ بعد از «مانده» (برای opening و current balance).
- مبلغ برداشت/واریز و تاریخ را نادیده بگیر و هرگز تراکنش نساز.
- name (نام نمایشی حساب) و bank_name (نام بانک) الزامی‌اند.
- اگر هر کدام در کل مکالمه مشخص نیست، ابزار را صدا نزن؛ فقط همان فیلدهای ناقص را در یک سؤال کوتاه بپرس. مثال: «نام بانک و یک نام نمایشی برای حساب را بفرمایید.»
- هرگز نام بانک یا نام حساب را از روی شماره حساب حدس نزن.
- اگر کاربر در همان پیام اول نام بانک و نام حساب را گفته، بلافاصله ابزار create_bank_account را صدا بزن.
- وقتی کاربر در نوبت بعد پاسخ داد، با داده‌های پیامک قبلی ابزار را صدا بزن و دوباره سؤال نکن.
- currency_code را فقط وقتی کاربر ارز گفته بفرست؛ وگرنه خالی بگذار.
- خروجی فارسی ابزار را عیناً به کاربر منتقل کن. اگر حساب تکراری بود دوباره نساز.

اگر قصد ثبت تراکنش است (ابزار create_transaction):
- هرگز حساب بانکی جدید نساز و create_bank_account را صدا نزن.
- برداشت = expense، واریز = income.
- مبلغ = مبلغ برداشت/واریز در پیامک (نه مانده).
- حساب را فقط از حساب‌های موجود پیدا کن (شماره حساب یا نامی که کاربر گفته).
- اگر حساب موجود مشخص نیست، ابزار را صدا نزن؛ فقط بپرس کدام حساب موجود است (نام یا شماره).
- تاریخ را از پیامک بگیر؛ اگر نبود امروز را بفرست.
- party و category نفرست مگر کاربر صریحاً گفته باشد.
- خروجی فارسی ابزار را عیناً به کاربر منتقل کن.

اگر پیام هیچ‌کدام نبود، عادی و مختصر پاسخ بده.
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
}
