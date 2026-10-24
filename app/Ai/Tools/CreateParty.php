<?php

namespace App\Ai\Tools;

use App\Enums\PartyType;
use App\Services\Accounting\PartyRegistrar;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateParty implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'ایجاد طرف‌حساب جدید وقتی کاربر صریحاً خواسته یا ایمپورت فاکتور به‌خاطر نبود طرف‌حساب خطا داده. اگر طرف‌حساب با همان نام یا نام حقوقی از قبل باشد ایجاد نکن و خطا بده. نام را حدس نزن؛ عین متن کاربر یا نام خریدار فایل را بفرست.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($user === null || $businessId === null) {
            return __('general.business_required');
        }

        $type = trim((string) $request->string('type'));
        if ($type === '') {
            $type = PartyType::Individual->value;
        }

        $result = app(PartyRegistrar::class)->register(
            (int) $businessId,
            $user->id,
            [
                'name' => (string) $request->string('name'),
                'legal_name' => (string) $request->string('legal_name'),
                'type' => $type,
                'economic_code' => (string) $request->string('economic_code'),
                'national_id' => (string) $request->string('national_id'),
                'phone' => (string) $request->string('phone'),
                'mobile' => (string) $request->string('mobile'),
                'email' => (string) $request->string('email'),
                'address' => (string) $request->string('address'),
                'postal_code' => (string) $request->string('postal_code'),
                'is_customer' => $request->has('is_customer') ? $request->boolean('is_customer') : true,
                'is_supplier' => $request->has('is_supplier') ? $request->boolean('is_supplier') : false,
                'contact_name' => (string) $request->string('contact_name'),
                'contact_mobile' => (string) $request->string('contact_mobile'),
                'contact_phone' => (string) $request->string('contact_phone'),
                'contact_email' => (string) $request->string('contact_email'),
                'contact_position' => (string) $request->string('contact_position'),
            ],
        );

        if (! $result['ok']) {
            return $result['message'];
        }

        $party = $result['party'];

        return __('general.ai_party_created', [
            'name' => $party->displayName(),
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('نام طرف‌حساب؛ عین نام خریدار فایل یا متن کاربر. هرگز حدس نزن.')
                ->required(),
            'legal_name' => $schema->string()
                ->description('نام حقوقی در صورت وجود'),
            'type' => $schema->string()
                ->description('individual یا company؛ پیش‌فرض individual'),
            'economic_code' => $schema->string()
                ->description('کد اقتصادی ۱۲ رقمی در صورت وجود در فایل یا پیام'),
            'national_id' => $schema->string()
                ->description('کد/شناسه ملی در صورت وجود'),
            'phone' => $schema->string()
                ->description('تلفن ثابت'),
            'mobile' => $schema->string()
                ->description('موبایل مثل 09121234567'),
            'email' => $schema->string()
                ->description('ایمیل'),
            'address' => $schema->string()
                ->description('نشانی کامل'),
            'postal_code' => $schema->string()
                ->description('کد پستی ۱۰ رقمی'),
            'is_customer' => $schema->boolean()
                ->description('مشتری است؟ پیش‌فرض true'),
            'is_supplier' => $schema->boolean()
                ->description('تأمین‌کننده است؟ پیش‌فرض false'),
            'contact_name' => $schema->string()
                ->description('نام فرد رابط در صورت نیاز'),
            'contact_mobile' => $schema->string()
                ->description('موبایل فرد رابط'),
            'contact_phone' => $schema->string()
                ->description('تلفن فرد رابط'),
            'contact_email' => $schema->string()
                ->description('ایمیل فرد رابط'),
            'contact_position' => $schema->string()
                ->description('سمت فرد رابط'),
        ];
    }
}
