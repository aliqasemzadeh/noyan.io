<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LeaveImpersonationAction
{
    public function handle(): User
    {
        if (! Impersonation::isImpersonating()) {
            throw ValidationException::withMessages([
                'impersonate' => __('general.impersonation_not_active'),
            ]);
        }

        $impersonator = Impersonation::impersonator();

        if ($impersonator === null) {
            Impersonation::stop();

            throw ValidationException::withMessages([
                'impersonate' => __('general.impersonation_not_active'),
            ]);
        }

        Impersonation::stop();

        Auth::login($impersonator);

        session()->regenerate();

        return $impersonator;
    }
}
