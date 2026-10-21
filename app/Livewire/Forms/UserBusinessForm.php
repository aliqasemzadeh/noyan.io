<?php

namespace App\Livewire\Forms;

use App\Actions\Business\CreateBusinessAction;
use App\Actions\Business\UpdateBusinessAction;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Enums\Business\InvoiceOrientation;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use Sadegh19b\LaravelPersianValidation\Rules\IranianNationalId;
use Sadegh19b\LaravelPersianValidation\Rules\IranianPostalCode;

class UserBusinessForm extends Form
{
    public ?Business $business = null;

    public string $name = '';

    public string $type = '';

    public string $category = '';

    public string $legal_name = '';

    public string $phone = '';

    public string $address = '';

    public string $national_id = '';

    public string $economic_code = '';

    public string $postal_code = '';

    public string $tax_memory_id = '';

    public string $invoice_primary_color = '';

    public string $invoice_secondary_color = '';

    public string $invoice_orientation = InvoiceOrientation::Portrait->value;

    public TemporaryUploadedFile|UploadedFile|null $logo = null;

    public bool $remove_logo = false;

    /**
     * @var list<int|string>
     */
    public array $currency_ids = [];

    public function setModel(Business $business): void
    {
        $this->business = $business;
        $this->name = $business->name;
        $this->type = $business->type?->value ?? '';
        $this->category = $business->category?->value ?? '';
        $this->legal_name = $business->legal_name ?? '';
        $this->phone = $business->phone ?? '';
        $this->address = $business->address ?? '';
        $this->national_id = $business->national_id ?? '';
        $this->economic_code = $business->economic_code ?? '';
        $this->postal_code = $business->postal_code ?? '';
        $this->tax_memory_id = $business->tax_memory_id ?? '';
        $this->invoice_primary_color = $business->invoice_primary_color ?? '';
        $this->invoice_secondary_color = $business->invoice_secondary_color ?? '';
        $this->invoice_orientation = $business->invoiceOrientation()->value;
        $this->logo = null;
        $this->remove_logo = false;
        $this->currency_ids = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $nationalIdRules = ['nullable', 'string', 'max:20'];
        if ($this->national_id !== '') {
            $nationalIdRules[] = new IranianNationalId;
        }

        $postalRules = ['nullable', 'string', 'max:16'];
        if ($this->postal_code !== '') {
            $postalRules[] = new IranianPostalCode;
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(BusinessType::class)],
            'category' => ['required', Rule::enum(BusinessCategory::class)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'national_id' => $nationalIdRules,
            'economic_code' => ['nullable', 'string', 'regex:/^\d{12}$/'],
            'postal_code' => $postalRules,
            'tax_memory_id' => ['nullable', 'string', 'max:64'],
            'invoice_primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_orientation' => ['required', Rule::enum(InvoiceOrientation::class)],
            'logo' => ['nullable', 'image', 'max:5120'],
            'remove_logo' => ['boolean'],
        ];

        if ($this->business === null) {
            $rules['currency_ids'] = ['required', 'array', 'min:1'];
            $rules['currency_ids.*'] = [
                'integer',
                Rule::exists('currencies', 'id')->where(function ($query): void {
                    $query->where('is_system', true)
                        ->whereNull('business_id')
                        ->where('is_active', true)
                        ->whereNull('deleted_at');
                }),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'legal_name' => __('general.legal_name'),
            'type' => __('general.business_type'),
            'category' => __('general.business_category'),
            'phone' => __('general.phone'),
            'address' => __('general.address'),
            'national_id' => __('general.national_id'),
            'economic_code' => __('general.economic_code'),
            'postal_code' => __('general.postal_code'),
            'tax_memory_id' => __('general.tax_memory_id'),
            'invoice_primary_color' => __('general.invoice_primary_color'),
            'invoice_secondary_color' => __('general.invoice_secondary_color'),
            'invoice_orientation' => __('general.invoice_orientation'),
            'logo' => __('general.logo'),
            'currency_ids' => __('general.currencies'),
            'currency_ids.*' => __('general.currency'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'economic_code.regex' => __('general.economic_code_format'),
        ];
    }

    public function store(User $user, CreateBusinessAction $action): Business
    {
        $validated = $this->validate();

        $business = $action->handle($user, $validated);

        $this->reset();

        return $business;
    }

    public function update(UpdateBusinessAction $action): Business
    {
        $validated = $this->validate();

        $business = $action->handle($this->business, $validated);

        $this->setModel($business);

        return $business;
    }

    public function defaultCurrencyId(): ?int
    {
        return Currency::query()
            ->system()
            ->active()
            ->where('code', 'IRT')
            ->value('id')
            ?? Currency::query()
                ->system()
                ->active()
                ->orderBy('code')
                ->value('id');
    }

    public function clearLogo(): void
    {
        $this->logo = null;
    }

    public function markLogoForRemoval(): void
    {
        $this->logo = null;
        $this->remove_logo = true;
    }
}
