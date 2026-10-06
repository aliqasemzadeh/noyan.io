<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateBankAccount;
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

وقتی کاربر پیامک یا صورتحساب بانکی می‌فرستد (شامل حساب، برداشت/واریز، مانده، تاریخ):
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
- اگر پیام درخواست ساخت حساب بانکی نبود، عادی و مختصر پاسخ بده.
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
        ];
    }
}
