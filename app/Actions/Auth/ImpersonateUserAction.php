<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ImpersonateUserAction
{
    public function handle(User $actor, User $target): void
    {
        if (! $actor->can('user_impersonate')) {
            throw new AuthorizationException;
        }

        if (Impersonation::isImpersonating()) {
            throw ValidationException::withMessages([
                'impersonate' => __('general.impersonation_already_active'),
            ]);
        }

        if ($target->isAdministrator()) {
            throw ValidationException::withMessages([
                'impersonate' => __('general.impersonation_admin_forbidden'),
            ]);
        }

        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'impersonate' => __('general.impersonation_self_forbidden'),
            ]);
        }

        Impersonation::start($actor);

        Auth::login($target);

        session()->regenerate();
    }
}
