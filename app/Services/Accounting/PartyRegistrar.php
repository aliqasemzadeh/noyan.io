<?php

namespace App\Services\Accounting;

use App\Enums\PartyType;
use App\Models\Accounting\Party;
use App\Models\Accounting\PartyContact;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Sadegh19b\LaravelPersianValidation\Rules\IranianCompanyId;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;
use Sadegh19b\LaravelPersianValidation\Rules\IranianNationalId;
use Sadegh19b\LaravelPersianValidation\Rules\IranianPhone;
use Sadegh19b\LaravelPersianValidation\Rules\IranianPostalCode;

class PartyRegistrar
{
    /**
     * @param  array{
     *     name?: string,
     *     legal_name?: string|null,
     *     type?: string,
     *     economic_code?: string|null,
     *     national_id?: string|null,
     *     phone?: string|null,
     *     mobile?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     postal_code?: string|null,
     *     credit_limit?: string,
     *     is_customer?: bool,
     *     is_supplier?: bool,
     *     is_active?: bool,
     *     contact_name?: string|null,
     *     contact_mobile?: string|null,
     *     contact_phone?: string|null,
     *     contact_email?: string|null,
     *     contact_position?: string|null
     * }  $attributes
     * @return array{ok: true, party: Party}|array{ok: false, message: string}
     */
    public function register(int $businessId, ?int $userId, array $attributes): array
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $legalName = $this->nullableString($attributes['legal_name'] ?? null);

        if ($name === '') {
            return [
                'ok' => false,
                'message' => __('general.ai_party_missing_fields', [
                    'fields' => __('general.name'),
                ]),
            ];
        }

        $duplicate = $this->findDuplicate($businessId, $name, $legalName);

        if ($duplicate !== null) {
            return [
                'ok' => false,
                'message' => __('general.ai_party_already_exists', [
                    'name' => $duplicate->displayName(),
                ]),
            ];
        }

        $type = trim((string) ($attributes['type'] ?? PartyType::Individual->value));
        if ($type === '') {
            $type = PartyType::Individual->value;
        }

        $payload = [
            'type' => $type,
            'name' => $name,
            'legal_name' => $legalName,
            'economic_code' => $this->nullableString($attributes['economic_code'] ?? null),
            'national_id' => $this->nullableString($attributes['national_id'] ?? null),
            'phone' => $this->nullableString($attributes['phone'] ?? null),
            'mobile' => $this->nullableString($attributes['mobile'] ?? null),
            'email' => $this->nullableString($attributes['email'] ?? null),
            'address' => $this->nullableString($attributes['address'] ?? null),
            'postal_code' => $this->nullableString($attributes['postal_code'] ?? null),
            'credit_limit' => Money::normalize((string) ($attributes['credit_limit'] ?? '0')) ?: '0',
            'is_customer' => (bool) ($attributes['is_customer'] ?? true),
            'is_supplier' => (bool) ($attributes['is_supplier'] ?? false),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'balance' => '0',
            'business_id' => $businessId,
            'user_id' => $userId,
        ];

        try {
            $validated = $this->validate($payload);
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'message' => __('general.ai_party_invalid', [
                    'errors' => $exception->validator->errors()->first(),
                ]),
            ];
        }

        $party = Party::create($validated);
        Party::forgetOptionsCache($businessId);

        $contactName = $this->nullableString($attributes['contact_name'] ?? null);
        if ($contactName !== null) {
            PartyContact::create([
                'party_id' => $party->id,
                'name' => $contactName,
                'position' => $this->nullableString($attributes['contact_position'] ?? null),
                'phone' => $this->nullableString($attributes['contact_phone'] ?? null),
                'mobile' => $this->nullableString($attributes['contact_mobile'] ?? null),
                'email' => $this->nullableString($attributes['contact_email'] ?? null),
                'is_primary' => true,
            ]);
        }

        return [
            'ok' => true,
            'party' => $party,
        ];
    }

    public function findDuplicate(int $businessId, string $name, ?string $legalName = null): ?Party
    {
        $name = trim($name);
        $legalName = $legalName !== null ? trim($legalName) : null;

        if ($name === '' && ($legalName === null || $legalName === '')) {
            return null;
        }

        return Party::query()
            ->where('business_id', $businessId)
            ->where(function ($query) use ($name, $legalName): void {
                if ($name !== '') {
                    $query->where('name', $name)
                        ->orWhere('legal_name', $name);
                }

                if ($legalName !== null && $legalName !== '') {
                    $query->orWhere('name', $legalName)
                        ->orWhere('legal_name', $legalName);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function validate(array $payload): array
    {
        $nationalId = (string) ($payload['national_id'] ?? '');
        $phone = (string) ($payload['phone'] ?? '');
        $mobile = (string) ($payload['mobile'] ?? '');
        $postalCode = (string) ($payload['postal_code'] ?? '');
        $type = (string) ($payload['type'] ?? PartyType::Individual->value);

        $nationalIdRules = ['nullable', 'string', 'max:20'];
        if ($nationalId !== '') {
            $nationalIdRules[] = $type === PartyType::Company->value
                ? new IranianCompanyId
                : new IranianNationalId;
        }

        $phoneRules = ['nullable', 'string', 'max:32'];
        if ($phone !== '') {
            $phoneRules[] = new IranianPhone;
        }

        $mobileRules = ['nullable', 'string', 'max:32'];
        if ($mobile !== '') {
            $mobileRules[] = new IranianMobile(format: 'zero');
        }

        $postalRules = ['nullable', 'string', 'max:16'];
        if ($postalCode !== '') {
            $postalRules[] = new IranianPostalCode;
        }

        $validator = Validator::make(
            $payload,
            [
                'business_id' => ['required', 'integer'],
                'user_id' => ['nullable', 'integer'],
                'type' => ['required', Rule::enum(PartyType::class)],
                'name' => ['required', 'string', 'max:255'],
                'legal_name' => ['nullable', 'string', 'max:255'],
                'economic_code' => ['nullable', 'string', 'regex:/^\d{12}$/'],
                'national_id' => $nationalIdRules,
                'phone' => $phoneRules,
                'mobile' => $mobileRules,
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'postal_code' => $postalRules,
                'credit_limit' => ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/'],
                'balance' => ['required', 'string'],
                'is_customer' => [
                    'boolean',
                    function (string $attribute, mixed $value, Closure $fail) use ($payload): void {
                        if (! ($payload['is_customer'] ?? false) && ! ($payload['is_supplier'] ?? false)) {
                            $fail(__('general.party_role_required'));
                        }
                    },
                ],
                'is_supplier' => ['boolean'],
                'is_active' => ['boolean'],
            ],
            [
                'economic_code.regex' => __('general.economic_code_format'),
                'credit_limit.regex' => __('general.credit_limit_format'),
            ],
            [
                'type' => __('general.party_type'),
                'name' => __('general.name'),
                'legal_name' => __('general.legal_name'),
                'economic_code' => __('general.economic_code'),
                'national_id' => __('general.national_id'),
                'phone' => __('general.phone'),
                'mobile' => __('general.mobile'),
                'email' => __('general.email'),
                'address' => __('general.address'),
                'postal_code' => __('general.postal_code'),
                'credit_limit' => __('general.credit_limit'),
                'is_customer' => __('general.is_customer'),
                'is_supplier' => __('general.is_supplier'),
            ],
        );

        return $validator->validate();
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
