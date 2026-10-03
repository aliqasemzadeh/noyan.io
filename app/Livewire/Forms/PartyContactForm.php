<?php

namespace App\Livewire\Forms;

use App\Models\Accounting\Party;
use App\Models\Accounting\PartyContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Form;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;
use Sadegh19b\LaravelPersianValidation\Rules\IranianPhone;

class PartyContactForm extends Form
{
    public ?PartyContact $contact = null;

    public ?Party $party = null;

    public string $name = '';

    public string $position = '';

    public string $phone = '';

    public string $mobile = '';

    public string $email = '';

    public string $note = '';

    public bool $is_primary = false;

    public function setParty(Party $party): void
    {
        $this->party = $party;
    }

    public function setModel(PartyContact $contact): void
    {
        $this->contact = $contact;
        $this->party = $contact->party;
        $this->name = $contact->name;
        $this->position = $contact->position ?? '';
        $this->phone = $contact->phone ?? '';
        $this->mobile = $contact->mobile ?? '';
        $this->email = $contact->email ?? '';
        $this->note = $contact->note ?? '';
        $this->is_primary = $contact->is_primary;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $phoneRules = ['nullable', 'string', 'max:32'];
        if ($this->phone !== '') {
            $phoneRules[] = new IranianPhone;
        }

        $mobileRules = ['nullable', 'string', 'max:32'];
        if ($this->mobile !== '') {
            $mobileRules[] = new IranianMobile(format: 'zero');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => $phoneRules,
            'mobile' => $mobileRules,
            'email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'position' => __('general.contact_position'),
            'phone' => __('general.phone'),
            'mobile' => __('general.mobile'),
            'email' => __('general.email'),
            'note' => __('general.note'),
            'is_primary' => __('general.is_primary_contact'),
        ];
    }

    public function store(): PartyContact
    {
        if ($this->party === null) {
            throw ValidationException::withMessages([
                'name' => __('general.party_required'),
            ]);
        }

        $validated = $this->validate();
        $validated = $this->normalizeOptionalStrings($validated);
        $validated['party_id'] = $this->party->id;

        return DB::transaction(function () use ($validated): PartyContact {
            if ($validated['is_primary']) {
                $this->clearOtherPrimaryContacts();
            }

            $contact = PartyContact::create($validated);
            $this->resetFormState();

            return $contact;
        });
    }

    public function update(): void
    {
        $validated = $this->validate();
        $validated = $this->normalizeOptionalStrings($validated);

        DB::transaction(function () use ($validated): void {
            if ($validated['is_primary']) {
                $this->clearOtherPrimaryContacts($this->contact?->id);
            }

            $this->contact->update($validated);
            $this->resetFormState();
        });
    }

    protected function clearOtherPrimaryContacts(?int $exceptId = null): void
    {
        if ($this->party === null) {
            return;
        }

        PartyContact::query()
            ->where('party_id', $this->party->id)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeOptionalStrings(array $validated): array
    {
        foreach (['position', 'phone', 'mobile', 'email', 'note'] as $field) {
            $validated[$field] = ($validated[$field] ?? '') !== '' ? $validated[$field] : null;
        }

        return $validated;
    }

    protected function resetFormState(): void
    {
        $party = $this->party;
        $this->reset();
        $this->party = $party;
        $this->is_primary = false;
    }
}
