<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UpdateUserProfileAction
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: ?string,
     *     gender: ?string,
     *     birth_date: ?string,
     *     avatar?: TemporaryUploadedFile|UploadedFile|null,
     *     remove_avatar?: bool
     * }  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->forceFill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'gender' => $data['gender'],
            'birth_date' => $data['birth_date'],
        ])->save();

        $this->syncAvatar(
            $user,
            $data['avatar'] ?? null,
            (bool) ($data['remove_avatar'] ?? false),
        );

        return $user->refresh();
    }

    private function syncAvatar(
        User $user,
        TemporaryUploadedFile|UploadedFile|null $avatar,
        bool $removeAvatar,
    ): void {
        if ($avatar !== null) {
            $user
                ->addMedia($avatar)
                ->toMediaCollection('avatar');

            return;
        }

        if ($removeAvatar) {
            $user->clearMediaCollection('avatar');
        }
    }
}
