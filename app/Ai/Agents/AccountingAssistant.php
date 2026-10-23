<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateBankAccount;
use App\Ai\Tools\CreateTransaction;
use App\Ai\Tools\GetDailyExpenses;
use App\Ai\Tools\ImportInvoice;
use App\Ai\Tools\ImportQuote;
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
#[MaxSteps(7)]
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
        $business = $this->activeBusinessBlock();
        $accounts = $this->existingAccountsBlock();

        return <<<PROMPT
تو یک دستیار هوشمند حسابداری هستی. به فارسی و مختصر پاسخ بده.

{$business}

{$accounts}

## وظایف و تشخیص قصد
1) پاسخ به استعلام‌ها و سؤالات (مانند موجودی/مانده، تعداد حساب‌ها، اطلاعات حساب‌ها):
- اگر کاربر درباره مانده/موجودی یک یا چند حساب، تعداد حساب‌ها، یا مشخصات آنها پرسید، مستقیماً از بخش «حساب‌های بانکی/موجود این کسب‌وکار» پاسخ بده.
- مانده هر حساب و در صورت نیاز ارز/واحد پول را به زیبایی و با فرمت تفکیک‌شده (سه رقم سه رقم) یا دقیق گزارش کن.

2) گزارش هزینه‌های روز (get_daily_expenses) — وقتی کاربر هزینه/خرج امروز یا یک تاریخ مشخص را خواست:
- فوراً get_daily_expenses را صدا بزن (برای امروز date را خالی بگذار؛ برای تاریخ دیگر date را بفرست).
- خروجی فارسی ابزار را عیناً به کاربر بده؛ عددسازی یا حدس نزن.
- این کار ثبت تراکنش نیست؛ create_transaction را صدا نزن.

3) ثبت تراکنش (create_transaction) — اولویت با ثبت تراکنش است وقتی پیامک بانکی «برداشت» یا «واریز» دارد:
- متن شامل «برداشت» یا «واریز» است، یا
- کاربر گفته تراکنش ثبت کن، یا
- شماره حساب پیامک در لیست حساب‌های موجود بالاست.
در این حالت جریان دو مرحله‌ای الزامی است:
الف) پیش‌نمایش (بدون ثبت):
- create_transaction را با confirmed=false صدا بزن (با account_number از پیامک).
- اگر چند پیامک یا چند تراکنش در یک پیام بود، فقط یک بار create_transaction را با آرایه transactions و confirmed=false صدا بزن.
- همیشه «مانده»ی پیامک را در expected_balance بفرست؛ ابزار مبلغ را از مانده فعلی دیتابیس کم/زیاد می‌کند و با مانده پیامک مقایسه می‌کند.
- خروجی فارسی ابزار را عیناً به کاربر بده و صبر کن تا تأیید کند.
ب) ثبت واقعی فقط بعد از تأیید صریح کاربر («آره»، «بله»، «ثبت کن»، «تأیید» و مشابه):
- همان داده‌ها را دوباره با confirmed=true بفرست.
- بدون تأیید کاربر هرگز confirmed=true نگذار؛ حتی اگر کاربر اول گفته «ثبت کن»، اول پیش‌نمایش بگیر.
سایر قواعد:
- مبلغ = مبلغ برداشت/واریز (نه مانده). کارمزد کارت‌به‌کارت را خودت حساب نکن؛ ابزار در صورت نیاز جدا ثبت می‌کند.
- هرگز create_bank_account را صدا نزن.
- هرگز نام بانک یا نام نمایشی حساب را نپرس.
- برداشت = expense، واریز = income.
- تاریخ را از پیامک بگیر؛ اگر نبود امروز.
- اگر شماره حساب در لیست نبود، فقط بپرس کدام حساب موجود است؛ حساب جدید نساز.

4) ساخت حساب بانکی (create_bank_account) — فقط وقتی:
- کاربر صریحاً ساخت حساب خواسته («حساب بساز»، «حساب کن») و
- پیامک «برداشت/واریز» برای ثبت تراکنش نیست، و
- شماره حساب هنوز در لیست موجود نیست.
در این حالت:
- name و bank_name الزامی‌اند؛ اگر نبودند در یک سؤال کوتاه بپرس.
- هرگز از روی شماره حساب حدس نزن.
- مبلغ برداشت/واریز را نادیده بگیر؛ تراکنش نساز.
- مانده = opening/current balance.

5) وارد کردن پیش‌فاکتور / استعلام قیمت / فاکتور از اکسل (import_quote یا import_invoice):
- وقتی کاربر فایل اکسل آپلود کرده و در پیام `file_path=` آمده، یا گفته فایل را وارد/ایمپورت کن:
الف) اگر نام فایل یا متن کاربر شامل «فاکتور» است و «پیش فاکتور/پیش‌فاکتور/استعلام/پیشنهاد» نیست → import_invoice با همان file_path و confirmed=false.
ب) اگر شامل «پیش فاکتور»، «پیش‌فاکتور»، «استعلام»، «پیشنهاد قیمت» است، یا نام فایل پیش‌فروش است → import_quote با confirmed=false.
ج) اگر مطمئن نیستی، اول import_quote را با confirmed=false امتحان کن؛ اگر ابزار گفت نوع سند فاکتور است، همان مسیر را با import_invoice تکرار کن.
- خروجی فارسی ابزار را عیناً بده و صبر کن تا کاربر تأیید کند.
- فقط بعد از تأیید صریح («آره»، «بله»، «ثبت کن»، «تأیید») همان file_path را با confirmed=true بفرست.
- بدون تأیید هرگز confirmed=true نگذار.
- خودت اکسل را پارس نکن و اقلام را حدس نزن؛ فقط ابزار را صدا بزن.

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
            new GetDailyExpenses,
            new ImportQuote,
            new ImportInvoice,
        ];
    }

    protected function activeBusinessBlock(): string
    {
        $business = Auth::user()?->currentBusiness;

        if ($business === null) {
            return 'کسب‌وکار فعال: هیچ.';
        }

        return "کسب‌وکار فعال: {$business->name} (فقط روی همین کسب‌وکار تراکنش ثبت کن).";
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
