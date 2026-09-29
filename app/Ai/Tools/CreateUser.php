<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;
use Stringable;

class CreateUser implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'اضافه کردن یک کاربر جدید به سیستم با شماره موبایل';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', new IranianMobile(format: 'zero')],
        ]);

        $user = User::query()->firstOrCreate([
            'mobile' => $validated['mobile'],
        ]);

        if (! $user->wasRecentlyCreated) {
            return __('general.user_already_exists', ['mobile' => $user->mobile]);
        }

        return __('general.user_created', ['mobile' => $user->mobile]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'mobile' => $schema->string()
                ->description('شماره موبایل کاربر، مثل 09171234567')
                ->required(),
        ];
    }
}
