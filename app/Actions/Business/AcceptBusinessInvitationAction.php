<?php

namespace App\Actions\Business;

use App\Models\BusinessInvitation;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptBusinessInvitationAction
{
    public function handle(BusinessInvitation $invitation, User $user): BusinessUser
    {
        if ($invitation->trashed() || ! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => $invitation->isAccepted()
                    ? __('general.business_invitation_already_accepted')
                    : __('general.business_invitation_invalid'),
            ]);
        }

        if ($user->mobile !== $invitation->mobile) {
            throw ValidationException::withMessages([
                'invitation' => __('general.business_invitation_mobile_mismatch'),
            ]);
        }

        return DB::transaction(function () use ($invitation, $user): BusinessUser {
            $locked = BusinessInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'invitation' => __('general.business_invitation_invalid'),
                ]);
            }

            $membership = BusinessUser::withTrashed()
                ->where('business_id', $locked->business_id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership !== null) {
                if ($membership->trashed()) {
                    $membership->restore();
                }

                $membership->update([
                    'roles' => $locked->roles,
                    'permissions' => $locked->permissions,
                ]);
            } else {
                $membership = BusinessUser::query()->create([
                    'business_id' => $locked->business_id,
                    'user_id' => $user->id,
                    'roles' => $locked->roles,
                    'permissions' => $locked->permissions,
                ]);
            }

            $locked->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ])->save();

            $user->forgetBusinessesCache();
            $user->forceFill([
                'current_business_id' => $locked->business_id,
            ])->save();

            return $membership->fresh(['business', 'user']) ?? $membership;
        });
    }
}
