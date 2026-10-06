<?php

namespace App\Livewire\Forms;

use App\Enums\PartyType;
use App\Models\Accounting\Party;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;
use Sadegh19b\LaravelPersianValidation\Rules\IranianCompanyId;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;
use Sadegh19b\LaravelPersianValidation\Rules\IranianNationalId;
use Sadegh19b\LaravelPersianValidation\Rules\IranianPhone;
use Sadegh19b\LaravelPersianValidation\Rules\IranianPostalCode;

class PartyForm extends Form
{
    public ?Party $party = null;

    public string $type = PartyType::Individual->value;

    public string $name = '';

    public string $legal_name = '';

    public string $economic_code = '';

    public string $national_id = '';

    public string $phone = '';

    public string $mobile = '';

    public string $email = '';

    public string $address = '';

    public string $postal_code = '';

    public string $credit_limit = '0';

    public bool $is_customer = true;

    public bool $is_supplier = false;

    public bool $is_active = true;

    public function setModel(Party $party): void
    {
        $this->party = $party;
        $this->type = $party->type->value;
        $this->name = $party->name;
        $this->legal_name = $party->legal_name ?? '';
        $this->economic_code = $party->economic_code ?? '';
        $this->national_id = $party->national_id ?? '';
        $this->phone = $party->phone ?? '';
        $this->mobile = $party->mobile ?? '';
        $this->email = $party->email ?? '';
        $this->address = $party->address ?? '';
        $this->postal_code = $party->postal_code ?? '';
        $this->credit_limit = rtrim(rtrim((string) $party->credit_limit, '0'), '.') ?: '0';
        $this->is_customer = $party->is_customer;
        $this->is_supplier = $party->is_supplier;
        $this->is_active = $party->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $nationalIdRules = ['nullable', 'string', 'max:20'];
        if ($this->national_id !== '') {
            $nationalIdRules[] = $this->type === PartyType::Company->value
                ? new IranianCompanyId
                : new IranianNationalId;
        }

        $phoneRules = ['nullable', 'string', 'max:32'];
        if ($this->phone !== '') {
            $phoneRules[] = new IranianPhone;
        }

        $mobileRules = ['nullable', 'string', 'max:32'];
        if ($this->mobile !== '') {
            $mobileRules[] = new IranianMobile(format: 'zero');
        }

        $postalRules = ['nullable', 'string', 'max:16'];
        if ($this->postal_code !== '') {
            $postalRules[] = new IranianPostalCode;
        }

        return [
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
            'is_customer' => [
                'boolean',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->is_customer && ! $this->is_supplier) {
                        $fail(__('general.party_role_required'));
                    }
                },
            ],
            'is_supplier' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
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
            'is_active' => __('general.is_active'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'economic_code.regex' => __('general.economic_code_format'),
            'credit_limit.regex' => __('general.credit_limit_format'),
        ];
    }

    public function store(): Party
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'name' => __('general.business_required'),
            ]);
        }

        $this->credit_limit = Money::normalize($this->credit_limit);
        $validated = $this->validate();

        $validated['business_id'] = $businessId;
        $validated['balance'] = '0';
        $validated = $this->normalizeOptionalStrings($validated);
        $validated['credit_limit'] = $this->normalizeAmount((string) $validated['credit_limit']);

        $party = Party::create($validated);

        Party::forgetOptionsCache($businessId);

        $this->resetFormState();

        return $party;
    }

    public function update(): void
    {
        $this->credit_limit = Money::normalize($this->credit_limit);
        $validated = $this->validate();

        $validated = $this->normalizeOptionalStrings($validated);
        $validated['credit_limit'] = $this->normalizeAmount((string) $validated['credit_limit']);

        $this->party->update($validated);

        Party::forgetOptionsCache((int) $this->party->business_id);

        $this->resetFormState();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeOptionalStrings(array $validated): array
    {
        foreach (['legal_name', 'economic_code', 'national_id', 'phone', 'mobile', 'email', 'address', 'postal_code'] as $field) {
            $validated[$field] = ($validated[$field] ?? '') !== '' ? $validated[$field] : null;
        }

        return $validated;
    }

    protected function normalizeAmount(string $amount): string
    {
        $amount = Money::normalize($amount);

        if ($amount === '' || ! str_contains($amount, '.')) {
            return $amount === '' ? '0' : $amount;
        }

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }

    protected function resetFormState(): void
    {
        $this->reset();
        $this->type = PartyType::Individual->value;
        $this->credit_limit = '0';
        $this->is_customer = true;
        $this->is_supplier = false;
        $this->is_active = true;
    }
}
