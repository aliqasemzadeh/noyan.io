<?php

namespace App\Livewire\Forms;

use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class BusinessUserForm extends Form
{
    public ?Business $business = null;

    public ?int $user_id = null;

    public string $role = 'viewer';

    public function setBusiness(Business $business): void
    {
        $this->business = $business;
        $this->reset(['user_id', 'role']);
        $this->role = BusinessRole::Viewer->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->business === null || $value === null) {
                        return;
                    }

                    $exists = BusinessUser::query()
                        ->where('business_id', $this->business->id)
                        ->where('user_id', $value)
                        ->exists();

                    if ($exists) {
                        $fail(__('general.business_user_already_member'));
                    }
                },
            ],
            'role' => [
                'required',
                Rule::enum(BusinessRole::class)->except([BusinessRole::Owner]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'user_id' => __('general.user'),
            'role' => __('general.role'),
        ];
    }

    public function store(): BusinessUser
    {
        if ($this->business === null) {
            throw ValidationException::withMessages([
                'user_id' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();

        $membership = BusinessUser::withTrashed()
            ->where('business_id', $this->business->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($membership !== null) {
            if ($membership->trashed()) {
                $membership->restore();
            }

            $membership->update([
                'role' => $validated['role'],
            ]);
        } else {
            $membership = BusinessUser::create([
                'business_id' => $this->business->id,
                'user_id' => $validated['user_id'],
                'role' => $validated['role'],
            ]);
        }

        User::query()->find($validated['user_id'])?->forgetBusinessesCache();

        $this->reset(['user_id', 'role']);
        $this->role = BusinessRole::Viewer->value;

        return $membership;
    }
}
