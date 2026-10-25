<?php

namespace App\Livewire\Forms;

use App\Actions\User\UpdateUserProfileAction;
use App\Enums\Gender;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;

class UserForm extends Form
{
    public ?User $user = null;

    public string $mobile = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $gender = '';

    public string $birth_date = '';

    public TemporaryUploadedFile|UploadedFile|null $avatar = null;

    public bool $remove_avatar = false;

    public function setModel(User $user): void
    {
        $this->user = $user;
        $this->mobile = $user->mobile;
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
            'mobile' => [
                'required',
                'string',
                new IranianMobile(format: 'zero'),
                $this->user
                    ? Rule::unique('users', 'mobile')->ignore($this->user->id)
                    : 'unique:users,mobile',
            ],
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
            'mobile' => __('general.mobile'),
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

    public function store(): User
    {
        $this->validate();

        $user = User::create([
            'mobile' => $this->mobile,
        ]);

        app(UpdateUserProfileAction::class)->handle($user, $this->profilePayload());

        $this->reset();

        return $user->refresh();
    }

    public function update(): void
    {
        $this->validate();

        $this->user->update([
            'mobile' => $this->mobile,
        ]);

        app(UpdateUserProfileAction::class)->handle($this->user, $this->profilePayload());

        $this->reset();
    }

    /**
     * @return array{first_name: string, last_name: string, email: ?string, gender: ?string, birth_date: ?string, avatar: TemporaryUploadedFile|UploadedFile|null, remove_avatar: bool}
     */
    private function profilePayload(): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email !== '' ? $this->email : null,
            'gender' => $this->gender !== '' ? $this->gender : null,
            'birth_date' => $this->birth_date !== '' ? $this->birth_date : null,
            'avatar' => $this->avatar,
            'remove_avatar' => $this->remove_avatar,
        ];
    }
}
