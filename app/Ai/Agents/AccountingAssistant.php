<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateBankAccount;
use App\Ai\Tools\CreateInvoice;
use App\Ai\Tools\CreateParty;
use App\Ai\Tools\CreateTransaction;
use App\Ai\Tools\GetDailyExpenses;
use App\Ai\Tools\ImportBankStatement;
use App\Ai\Tools\ImportInvoice;
use App\Ai\Tools\ImportQuote;
use App\Enums\Business\BusinessPermission;
use App\Models\Accounting\Account;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

#[Provider('gap')]
#[MaxSteps(12)]
#[Timeout(240)]
class AccountingAssistant implements Agent, HasProviderOptions, HasTools, RemembersConversationsContract
{
    use Promptable;
    use RemembersConversations;

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            'gap' => ['reasoning_effort' => 'none'],
            default => [],
        };
    }

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

نکته: «حساب بانکی» در ابزارها همان کیف پول نقد/بانک است، نه سرفصل دفترکل (معین). ثبت تراکنش از پیامک روی دفتر نقدی می‌رود؛ اگر ثبت خودکار دفترکل روشن باشد، سیستم خودش سند معین را می‌سازد (دسته پیش‌فرض کارمزد/سایر درآمد).

## وظایف و تشخیص قصد
1) پاسخ به استعلام‌ها و سؤالات (مانند موجودی/مانده، تعداد حساب‌ها، اطلاعات حساب‌ها):
- اگر کاربر درباره مانده/موجودی یک یا چند حساب، تعداد حساب‌ها، یا مشخصات آنها پرسید، مستقیماً از بخش «حساب‌های بانکی/موجود این کسب‌وکار» پاسخ بده.
- مانده هر حساب و در صورت نیاز ارز/واحد پول را به زیبایی و با فرمت تفکیک‌شده (سه رقم سه رقم) یا دقیق گزارش کن.

2) گزارش هزینه‌های روز (get_daily_expenses) — وقتی کاربر هزینه/خرج امروز یا یک تاریخ مشخص را خواست:
- فوراً get_daily_expenses را صدا بزن (برای امروز date را خالی بگذار؛ برای تاریخ دیگر date را بفرست).
- خروجی فارسی ابزار را عیناً به کاربر بده؛ عددسازی یا حدس نزن.
- این کار ثبت تراکنش نیست؛ create_transaction را صدا نزن.

        3) ثبت تراکنش (create_transaction) — اولویت با ثبت تراکنش است وقتی پیامک بانکی «برداشت» یا «واریز» دارد، یا گزارش «گردش حساب»/صورتحساب بانکی جدولی آمده:
الف) پیامک:
- متن شامل «برداشت» یا «واریز» است، یا کاربر گفته تراکنش ثبت کن، یا شماره حساب پیامک در لیست حساب‌های موجود بالاست.
- همیشه «مانده»ی پیامک را در expected_balance بفرست.
- برداشت = expense، واریز = income.
ب) گزارش گردش حساب (جدول با ستون‌هایی مثل تاریخ، زمان، شرح، مبلغ، مانده؛ و هدر شماره حساب/شبا/کارت):
- الزامی: شناسه حساب مالک گزارش را فقط از هدر بگیر («شماره حساب» یا «کد شبا» یا کارت) و همان را یک‌بار در پارامتر ریشه account_number ابزار بفرست تا روی همه ردیف‌ها اعمال شود.
- هرگز این‌ها را به‌عنوان حساب مقصد ثبت نگیر:
  • ستون «واریز کننده / ذینفع» یا شبا/نام طرف مقابل در ردیف‌ها
  • «نام صاحب حساب» (مثل علی قاسم‌زاده) — این نام نمایشی حساب در سیستم نیست
- با لیست «حساب‌های موجود» مطابقت بده (مثلاً شماره 1266272158 یا شبا IR3001…). اگر در لیست بود همان شناسه را بفرست.
- هر ردیف جدول = یک آیتم در transactions؛ هیچ ردیفی را حذف/ادغام نکن (کارمزد پایا هم ردیف جداست).
- نوع هر ردیف را از مقایسه مانده با ردیف قبل مشخص کن: اگر مانده کم شد → expense؛ اگر زیاد شد → income. برای اولین ردیف از جهت تغییر نسبت به منطق مبلغ/مانده همان ردیف استفاده کن (مانده بعد از تراکنش = expected_balance).
- amount = ستون مبلغ همان ردیف؛ expected_balance = ستون مانده همان ردیف؛ note = شرح؛ reference_number = شماره مستند اگر هست؛ transaction_date = تاریخ + زمان (مثل 1405/08/02 08:30:06).
جریان مشترک:
- تشخیص شناسه حساب مالک در متن کاربر/پیامک/هدر گزارش (نه ذینفع ردیف):
  • شبا: با IR شروع می‌شود یا ۲۴ رقم بعد از IR → در account_number ریشه بفرست.
  • کارت: معمولاً ۱۶ رقم → در account_number ریشه بفرست.
  • شماره حساب: ارقام کوتاه‌تر/دیگر → در account_number ریشه بفرست.
  نوع را برای کاربر توضیح نده؛ فقط مقدار را بفرست — ابزار تطبیق می‌کند.
- اگر چند پیامک/ردیف در یک پیام بود، ترجیحاً یک بار create_transaction را با آرایه transactions صدا بزن؛ اگر بیش از حدود ۴۰ ردیف بود، در چند فراخوانی متوالی chunk بفرست (ترتیب زمانی حفظ شود).
- تاریخ/ساعت را عیناً از پیامک/ردیف بفرست (مثل 05/08/02-16:18 یا 1405/08/02 08:30:06)؛ سال را حدس نزن — ابزار کامل می‌کند.
- اگر مانده با دفتر جور باشد، ابزار همان لحظه ثبت می‌کند (بدون تأیید). خروجی را عیناً بده و دوباره تأیید نخواه.
- اگر بعضی ردیف‌ها از قبل در دفتر بودند، ابزار آن‌ها را skip می‌کند و بقیه را ثبت می‌کند.
- اگر ابزاری برای یک ردیف خطا داد (مثلاً مانده جور نیست)، دلیل فارسی ابزار را عیناً به کاربر بده و خلاصه/حدس نزن.
- فقط وقتی ابزار گفت مانده جور نیست یا مانده نبود و تأیید خواست، صبر کن؛ بعد از «آره»/«ثبت کن» همان داده را با confirmed=true بفرست.
سایر قواعد:
- مبلغ = مبلغ برداشت/واریز/ردیف (نه مانده). کارمزد کارت‌به‌کارت را خودت حساب نکن؛ ابزار در صورت نیاز جدا ثبت می‌کند. ردیف‌های «کارمزد» گردش حساب را خودت به‌عنوان expense جدا بفرست.
- هرگز create_bank_account را صدا نزن.
- هرگز نام بانک یا نام نمایشی حساب را نپرس.
- اگر شناسه (شماره/شبا/کارت) در لیست حساب‌های موجود نبود، فقط بپرس کدام حساب موجود است؛ حساب جدید نساز.

4) ساخت حساب بانکی (create_bank_account) — فقط وقتی:
- کاربر صریحاً ساخت حساب خواسته («حساب بساز»، «حساب کن») و
- پیامک «برداشت/واریز» برای ثبت تراکنش نیست، و
- شماره حساب هنوز در لیست موجود نیست.
در این حالت:
- name و bank_name الزامی‌اند؛ اگر نبودند در یک سؤال کوتاه بپرس.
- هرگز از روی شماره حساب حدس نزن.
- مبلغ برداشت/واریز را نادیده بگیر؛ تراکنش نساز.
- مانده = opening/current balance.

        5) ثبت فاکتور فروش/خرید از متن (create_invoice) — وقتی:
- کاربر متن فاکتور، لیست اقلام، یا دستور «فاکتور بساز/ثبت کن» داده و فایل اکسل آپلود نشده.
تشخیص نوع (الزامی قبل از صدا زدن ابزار):
- فروش (sale): «فاکتور فروش»، «فروختم»، «به مشتری»، «برای خریدار»، طرف‌حساب مشتری.
- خرید (purchase): «فاکتور خرید»، «خریدم»، «از تأمین‌کننده/فروشنده»، «هزینه خرید کالا»، طرف‌حساب تأمین‌کننده.
- اگر فقط گفته «فاکتور» و نوع مشخص نیست: create_invoice را صدا نزن؛ در یک سؤال کوتاه بپرس «این فاکتور فروش است یا خرید؟» و بعد از پاسخ ادامه بده. نوع را هرگز حدس نزن.
جریان:
- type را فقط وقتی مشخص است بفرست: فروش = sale، خرید = purchase.
- party_name و items (title، quantity، unit_price) را از متن استخراج کن؛ مبلغ را حدس نزن.
- ابتدا create_invoice را با confirmed=false صدا بزن؛ خروجی را عیناً بده و صبر کن.
- فقط بعد از تأیید صریح («آره»، «بله»، «ثبت کن»، «تأیید») همان داده را با confirmed=true بفرست.
- بدون تأیید هرگز confirmed=true نگذار.
- اگر طرف‌حساب نبود، create_party را صدا بزن (برای فروش is_customer=true؛ برای خرید is_supplier=true)؛ بعد دوباره create_invoice.
- برای فایل اکسل رسمی از این ابزار استفاده نکن.

6) وارد کردن گردش حساب بانکی CSV (import_bank_statement):
- وقتی فایل CSV گردش/صورتحساب بانک آپلود شده (`file_path=` با پسوند .csv)، یا کاربر گفته صورتحساب/گردش حساب بانک را وارد کن:
- فوراً import_bank_statement را با همان file_path و confirmed=false صدا بزن.
- اگر شماره حساب در نام فایل یا متن کاربر هست، در account_number بفرست؛ وگرنه ابزار از نام فایل حدس می‌زند.
- خروجی فارسی ابزار را عیناً بده؛ مسیر فایل را نشان نده.
- فقط بعد از تأیید صریح همان file_path را با confirmed=true بفرست.
- CSV بانکی را با import_invoice/import_quote یا create_transaction تکی ثبت نکن.

7) وارد کردن پیش‌فاکتور / استعلام قیمت / فاکتور از اکسل (import_quote یا import_invoice):
- وقتی کاربر فایل اکسل (.xlsx/.xls) آپلود کرده و در پیام داخلی `file_path=` آمده، یا گفته فایل را وارد/ایمپورت کن:
الف) اگر نام فایل یا متن کاربر شامل «فاکتور» است و «پیش فاکتور/پیش‌فاکتور/استعلام/پیشنهاد» نیست → import_invoice با همان file_path و confirmed=false.
ب) اگر شامل «پیش فاکتور»، «پیش‌فاکتور»، «استعلام»، «پیشنهاد قیمت» است، یا نام فایل پیش‌فروش است → import_quote با confirmed=false.
ج) اگر مطمئن نیستی، اول import_quote را با confirmed=false امتحان کن؛ اگر ابزار گفت نوع سند فاکتور است، همان مسیر را با import_invoice تکرار کن.
- خروجی فارسی ابزار را عیناً بده و صبر کن تا کاربر تأیید کند. هرگز مسیر فایل (file_path) را به کاربر نشان نده.
- فقط بعد از تأیید صریح («آره»، «بله»، «ثبت کن»، «تأیید») همان file_path را با confirmed=true بفرست.
- بعد از تأیید، ابزار ثبت را در صف می‌گذارد و پیام صف را برمی‌گرداند؛ همان را عیناً بده و منتظر نتیجهٔ ثبت نمان.
- بدون تأیید هرگز confirmed=true نگذار.
- خودت اکسل را پارس نکن و اقلام را حدس نزن؛ فقط ابزار را صدا بزن.
- متنِ فاکتور بدون فایل اکسل را با import_invoice ثبت نکن؛ از create_invoice استفاده کن.
- اگر ابزار گفت طرف‌حساب یافت نشد، create_party را با عین نام و فیلدهای گزارش‌شده صدا بزن (حدس نزن)؛ بعد از ساخت موفق دوباره ایمپورت را پیشنهاد بده.
- فایل CSV بانکی را با این ابزارها وارد نکن؛ از import_bank_statement استفاده کن.

8) ساخت طرف‌حساب (create_party) — وقتی:
- کاربر صریحاً طرف‌حساب/مشتری/خریدار/تأمین‌کننده بساز خواسته، یا
- ایمپورت یا create_invoice به‌خاطر نبود طرف‌حساب خطا داده است.
قواعد:
- name الزامی است؛ عین نام خریدار/فروشنده فایل یا متن کاربر را بفرست؛ هرگز کوتاه/حدس نکن.
- اگر در پیام یا خطای ابزار کد ملی، کد اقتصادی، تلفن، آدرس یا کد پستی آمده، همان‌ها را هم بفرست.
- برای فاکتور فروش is_customer=true؛ برای فاکتور خرید is_supplier=true.
- اگر طرف‌حساب با همان نام یا نام حقوقی از قبل باشد، ابزار خطا می‌دهد؛ دوباره نساز.
- بعد از موفقیت، پیام ابزار را عیناً بده.

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
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $tools = [];

        if ($user->canInBusiness(BusinessPermission::AccountCreate)) {
            $tools[] = new CreateBankAccount;
        }

        if ($user->canInBusiness(BusinessPermission::TransactionCreate)) {
            $tools[] = new CreateTransaction;
            $tools[] = new ImportBankStatement;
        }

        if ($user->canInBusiness(BusinessPermission::TransactionView)) {
            $tools[] = new GetDailyExpenses;
        }

        if ($user->canInBusiness(BusinessPermission::QuoteCreate)) {
            $tools[] = new ImportQuote;
        }

        if ($user->canInBusiness(BusinessPermission::InvoiceCreate)) {
            $tools[] = new CreateInvoice;
            $tools[] = new ImportInvoice;
        }

        if ($user->canInBusiness(BusinessPermission::PartyCreate)) {
            $tools[] = new CreateParty;
        }

        return $tools;
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
        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($businessId === null) {
            return 'حساب‌های موجود: هیچ (کسب‌وکار فعال نیست).';
        }

        if (! $user instanceof User || ! $user->canInBusiness(BusinessPermission::AccountView)) {
            return 'حساب‌های موجود: دسترسی مشاهده حساب ندارید.';
        }

        $accounts = Account::query()
            ->with('currency')
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'account_number', 'card_number', 'iban', 'bank_name', 'current_balance', 'currency_id']);

        if ($accounts->isEmpty()) {
            return 'حساب‌های موجود: هیچ.';
        }

        $lines = $accounts->map(function (Account $account): string {
            $number = $account->account_number ?: '—';
            $iban = $account->iban ?: '—';
            $card = $account->card_number ?: '—';
            $bank = $account->bank_name ?: '—';
            $currency = $account->currency?->name ?: ($account->currency?->code ?: '');
            $balance = number_format((float) ($account->current_balance ?? 0), 0, '.', ',');
            $balanceStr = $currency !== '' ? "{$balance} {$currency}" : $balance;

            return "- {$account->name} | بانک: {$bank} | شماره: {$number} | شبا: {$iban} | کارت: {$card} | مانده/موجودی: {$balanceStr}";
        })->implode("\n");

        return "حساب‌های بانکی/موجود این کسب‌وکار (هم برای پاسخ به استعلام مانده/مشخصات حساب و هم فقط از همین‌ها برای ثبت تراکنش استفاده کن؛ شناسه می‌تواند شماره یا شبا یا کارت باشد):\n{$lines}";
    }
}
