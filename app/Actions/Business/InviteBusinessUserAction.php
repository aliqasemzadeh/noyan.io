<?php

namespace App\Actions\Business;

use App\Jobs\Notification\User\SendBusinessInvitationJob;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessUser;
use App\Models\User;
use App\Settings\InvoiceSystemSettings;
use App\Support\ShortLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InviteBusinessUserAction
{
    public function __construct(
        private ShortLinkService $shortLinks,
        private DebitBusinessSmsWalletAction $debitWallet,
        private InvoiceSystemSettings $systemSettings,
    ) {}

    /**
     * @param  list<string>  $roles
     * @param  array<string, list<string>>  $permissions
     */
    public function handle(
        Business $business,
        string $mobile,
        array $roles,
        array $permissions,
        User $invitedBy,
    ): BusinessInvitation {
        $existingMember = BusinessUser::query()
            ->where('business_id', $business->id)
            ->whereHas('user', fn ($query) => $query->where('mobile', $mobile))
            ->exists();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'mobile' => __('general.business_user_already_member'),
            ]);
        }

        $pending = BusinessInvitation::query()
            ->pending()
            ->where('business_id', $business->id)
            ->where('mobile', $mobile)
            ->first();

        if ($pending !== null) {
            throw ValidationException::withMessages([
                'mobile' => __('general.business_invitation_already_pending'),
            ]);
        }

        $cost = (string) $this->systemSettings->sms_unit_cost;

        if (bccomp((string) $business->sms_wallet_balance, $cost, 2) === -1) {
            throw ValidationException::withMessages([
                'mobile' => __('general.sms_wallet_insufficient_balance'),
            ]);
        }

        return DB::transaction(function () use ($business, $mobile, $roles, $permissions, $invitedBy, $cost): BusinessInvitation {
            $invitation = BusinessInvitation::query()->create([
                'business_id' => $business->id,
                'mobile' => $mobile,
                'roles' => $roles,
                'permissions' => $permissions,
                'token' => Str::random(64),
                'invited_by' => $invitedBy->id,
                'expires_at' => now()->addDays(7),
            ]);

            $shortUrl = $this->shortLinks->create(
                destination: $invitation->acceptUrl(),
                business: $business,
                expiresAt: $invitation->expires_at,
            );

            try {
                $this->debitWallet->handle(
                    $business->fresh(),
                    $cost,
                    __('general.sms_wallet_sms_debit'),
                    $invitation,
                );
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'mobile' => $exception->getMessage(),
                ]);
            }

            SendBusinessInvitationJob::dispatch($invitation, $shortUrl);

            return $invitation;
        });
    }
}
