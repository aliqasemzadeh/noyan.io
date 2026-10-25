<?php

namespace App\Actions\Business;

use App\Models\BusinessInvitation;
use Illuminate\Validation\ValidationException;

class CancelBusinessInvitationAction
{
    public function handle(BusinessInvitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'invitation' => __('general.business_invitation_already_accepted'),
            ]);
        }

        if ($invitation->trashed()) {
            return;
        }

        $invitation->delete();
    }
}
