<?php

namespace App\Livewire\Forms;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class UserProfileForm extends Form
{
    public ?User $user = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $gender = '';

    public string $birth_date = '';

    public TemporaryUploadedFile|UploadedFile|null $avatar = null;

    public bool $remove_avatar = false;

    public function setFromUser(User $user): void
    {
        $this->user = $user;
        $this->first_name = $user->first_name ?? '';
        $this->last_name = $user->last_name ?? '';
        $this->email = $user->email ?? '';
        $this->gender = $user->gender?->value ?? '';
        $this->birth_date = $user->birth_date?->format('Y-m-d') ?? '';
        $this->avatar = null;
        $this->remove_avatar = false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user?->id),
            ],
            'gender' => ['nullable', 'string', Rule::in(['', ...Gender::values()])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'remove_avatar' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'first_name' => __('general.first_name'),
            'last_name' => __('general.last_name'),
            'email' => __('general.email'),
            'gender' => __('general.gender'),
            'birth_date' => __('general.birth_date'),
            'avatar' => __('general.avatar'),
        ];
    }

    public function clearAvatar(): void
    {
        $this->avatar = null;
    }

    public function markAvatarForRemoval(): void
    {
        $this->avatar = null;
        $this->remove_avatar = true;
    }

    /**
     * @return array{first_name: string, last_name: string, email: ?string, gender: ?string, birth_date: ?string, avatar: TemporaryUploadedFile|UploadedFile|null, remove_avatar: bool}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validate();

        return [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => ($validated['email'] ?? '') !== '' ? $validated['email'] : null,
            'gender' => ($validated['gender'] ?? '') !== '' ? $validated['gender'] : null,
            'birth_date' => ($validated['birth_date'] ?? '') !== '' ? $validated['birth_date'] : null,
            'avatar' => $this->avatar,
            'remove_avatar' => (bool) $this->remove_avatar,
        ];
    }
}
