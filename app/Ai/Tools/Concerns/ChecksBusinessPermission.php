<?php

namespace App\Ai\Tools\Concerns;

use App\Enums\Business\BusinessPermission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait ChecksBusinessPermission
{
    protected function authenticatedUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function denyUnlessCan(BusinessPermission $permission): ?string
    {
        $user = $this->authenticatedUser();

        if ($user === null || $user->current_business_id === null) {
            return __('general.business_required');
        }

        if (! $user->canInBusiness($permission)) {
            return __('general.ai_permission_denied');
        }

        return null;
    }
}
