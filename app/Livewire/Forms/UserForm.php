<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;

class UserForm extends Form
{
    public ?User $user = null;

    public string $mobile = '';

    public function setModel(User $user): void
    {
        $this->user = $user;
        $this->mobile = $user->mobile;
    }

    public function rules(): array
    {
        return [
            'mobile' => [
                'required',
                'string',
                new IranianMobile(format: 'zero'),
                $this->user
                    ? Rule::unique('users', 'mobile')->ignore($this->user->id)
                    : 'unique:users,mobile',
            ],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'mobile' => __('general.mobile'),
        ];
    }

    public function store(): User
    {
        $validated = $this->validate();

        $user = User::create($validated);

        $this->reset();

        return $user;
    }

    public function update(): void
    {
        $validated = $this->validate();

        $this->user->update($validated);

        $this->reset();
    }
}
