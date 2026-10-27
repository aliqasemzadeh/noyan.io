<?php

use App\Actions\Business\AcceptBusinessInvitationAction;
use App\Jobs\Notification\User\SendUserOtpJob;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Settings\SecuritySettings;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;
use Spatie\OneTimePasswords\Enums\ConsumeOneTimePasswordResult;

new
#[Layout('layouts::auth')]
class extends Component
{
    public string $step = 'mobile';

    public string $mobile = '';

    public string $code = '';

    public ?int $resendAvailableAt = null;

    public ?string $debugOtpCode = null;

    #[Url]
    public ?string $invitation = null;

    public function mount(): void
    {
        $token = $this->invitation ?: session('business_invitation_token');

        if (is_string($token) && $token !== '') {
            $this->invitation = $token;
            session(['business_invitation_token' => $token]);

            $invite = BusinessInvitation::query()->where('token', $token)->pending()->first();

            if ($invite !== null) {
                $this->mobile = $invite->mobile;
            }
        }
    }

    public function sendCode(): void
    {
        $this->mobile = Money::normalize($this->mobile);

        $this->validate([
            'mobile' => ['required', 'string', new IranianMobile(format: 'zero')],
        ], attributes: [
            'mobile' => __('general.mobile'),
        ]);

        if ($this->isMobileBanned($this->mobile)) {
            $this->addError('mobile', __('general.mobile_banned'));
            Flux::toast(__('general.mobile_banned'), variant: 'danger');

            return;
        }

        if ($this->resendAvailableAt !== null && now()->getTimestamp() < $this->resendAvailableAt) {
            $this->addError('mobile', __('general.otp_resend_wait'));
            Flux::toast(__('general.otp_resend_wait'), variant: 'warning');

            return;
        }

        $user = User::query()->withTrashed()->firstOrCreate([
            'mobile' => $this->mobile,
        ]);

        if ($user->trashed()) {
            $user->restore();
        }

        $oneTimePassword = $user->createOneTimePassword(
            (int) config('otp.expires_in_minutes'),
        );

        SendUserOtpJob::dispatch($user, $oneTimePassword->password);

        $this->step = 'otp';
        $this->code = '';
        $this->resendAvailableAt = now()
            ->addSeconds((int) config('otp.resend_cooldown_seconds'))
            ->getTimestamp();

        if (app()->isLocal() || config('app.debug')) {
            $this->debugOtpCode = $oneTimePassword->password;
        }

        Flux::toast(__('general.otp_sent'));
    }

    public function verify(): mixed
    {
        if (auth()->check()) {
            return $this->redirectIntended(default: route('dashboard'), navigate: false);
        }

        $this->mobile = Money::normalize($this->mobile);
        $this->code = Money::normalize($this->code);

        $this->validate([
            'mobile' => ['required', 'string', new IranianMobile(format: 'zero')],
            'code' => ['required', 'string', 'digits:6'],
        ], attributes: [
            'mobile' => __('general.mobile'),
            'code' => __('general.otp_code'),
        ]);

        if ($this->isMobileBanned($this->mobile)) {
            $this->addError('mobile', __('general.mobile_banned'));
            Flux::toast(__('general.mobile_banned'), variant: 'danger');

            return null;
        }

        $user = User::query()->where('mobile', $this->mobile)->first();

        if ($user === null) {
            $this->addError('code', __('general.otp_invalid'));
            Flux::toast(__('general.otp_invalid'), variant: 'danger');

            return null;
        }

        $result = $user->attemptLoginUsingOneTimePassword($this->code, remember: true);

        if (! $result->isOk()) {
            $message = match ($result) {
                ConsumeOneTimePasswordResult::IncorrectOneTimePassword,
                ConsumeOneTimePasswordResult::NoOneTimePasswordsFound,
                ConsumeOneTimePasswordResult::DifferentOrigin => __('general.otp_invalid'),
                ConsumeOneTimePasswordResult::OneTimePasswordExpired => __('general.otp_expired'),
                ConsumeOneTimePasswordResult::RateLimitExceeded => __('general.otp_rate_limited'),
                default => $result->validationMessage(),
            };

            $this->addError('code', $message);
            Flux::toast($message, variant: 'danger');

            return null;
        }

        session()->regenerate();

        $redirect = $this->acceptPendingInvitation($user);

        if ($redirect !== null) {
            return $redirect;
        }

        $user->ensureCurrentBusiness();

        Flux::toast(__('general.login_success'));

        return $this->redirectIntended(default: route('dashboard'), navigate: false);
    }

    protected function acceptPendingInvitation(User $user): mixed
    {
        $token = $this->invitation ?: session('business_invitation_token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        $invitation = BusinessInvitation::query()
            ->where('token', $token)
            ->pending()
            ->first();

        if ($invitation === null) {
            session()->forget('business_invitation_token');

            return null;
        }

        try {
            app(AcceptBusinessInvitationAction::class)->handle($invitation, $user);
        } catch (ValidationException $exception) {
            session()->forget('business_invitation_token');

            $message = collect($exception->errors())->flatten()->first()
                ?: __('general.business_invitation_invalid');

            Flux::toast($message, variant: 'danger');

            return $this->redirect(route('dashboard'), navigate: false);
        }

        session()->forget('business_invitation_token');

        Flux::toast(__('general.business_invitation_accepted'));

        return $this->redirect(route('user.dashboard'), navigate: false);
    }

    public function resendCode(): void
    {
        if ($this->resendAvailableAt !== null && now()->getTimestamp() < $this->resendAvailableAt) {
            $this->addError('code', __('general.otp_resend_wait'));
            Flux::toast(__('general.otp_resend_wait'), variant: 'warning');

            return;
        }

        $this->sendCode();
    }

    public function backToMobile(): void
    {
        $this->step = 'mobile';
        $this->code = '';
        $this->debugOtpCode = null;
        $this->resendAvailableAt = null;
        $this->resetErrorBag();
    }

    protected function isMobileBanned(string $mobile): bool
    {
        try {
            $banned = app(SecuritySettings::class)->banned_mobiles;
        } catch (\Throwable) {
            return false;
        }

        return in_array($mobile, $banned, true);
    }

    #[Computed]
    public function maskedMobile(): string
    {
        if (strlen($this->mobile) < 7) {
            return $this->mobile;
        }

        return substr($this->mobile, 0, 4).'***'.substr($this->mobile, -4);
    }
};
?>

<x-slot name="title">{{ __('general.login') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div class="space-y-2 text-center">
        <div class="flex justify-center opacity-70">
            <span class="text-xl font-semibold text-zinc-800 dark:text-white">{{ __('general.app_name') }}</span>
        </div>

        <flux:heading class="text-center" size="xl">{{ __('general.welcome_back') }}</flux:heading>
    </div>

    @if ($step === 'mobile')
        <form wire:submit="sendCode" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('general.mobile') }}</flux:label>
                <flux:input
                    wire:model="mobile"
                    type="tel"
                    inputmode="numeric"
                    maxlength="11"
                    placeholder="{{ __('general.mobile_placeholder') }}"
                    clearable
                    dir="ltr"
                    autocomplete="tel"
                />
                <flux:error name="mobile" />
            </flux:field>

            <flux:button type="submit" variant="primary" color="teal" class="w-full">
                {{ __('general.send_otp') }}
            </flux:button>
        </form>
    @else
        <form
            wire:submit="verify"
            class="space-y-6"
            x-on:submit.capture="
                if (window.__noyanOtpVerifyLock) {
                    $event.preventDefault()
                    $event.stopImmediatePropagation()
                } else {
                    window.__noyanOtpVerifyLock = Date.now()
                }
            "
            x-data="{
            remaining: Math.max(0, ($wire.resendAvailableAt ?? 0) - Math.floor(Date.now() / 1000)),
            timer: null,
            abortController: null,
            init() {
                this.tick()
                this.timer = setInterval(() => this.tick(), 1000)
                this.listenForWebOtp()
                this.registerOtpLockRelease()
            },
            registerOtpLockRelease() {
                if (window.__noyanOtpHooked) {
                    return
                }

                window.__noyanOtpHooked = true

                const register = () => {
                    Livewire.hook('commit', ({ succeed, fail }) => {
                        succeed(() => {
                            const token = window.__noyanOtpVerifyLock
                            setTimeout(() => {
                                if (window.__noyanOtpVerifyLock === token) {
                                    window.__noyanOtpVerifyLock = false
                                }
                            }, 0)
                        })
                        fail(() => { window.__noyanOtpVerifyLock = false })
                    })
                }

                if (window.Livewire) {
                    register()
                } else {
                    document.addEventListener('livewire:init', register, { once: true })
                }
            },
            tick() {
                const availableAt = $wire.resendAvailableAt ?? 0
                this.remaining = Math.max(0, availableAt - Math.floor(Date.now() / 1000))
                if (this.remaining === 0 && this.timer) {
                    clearInterval(this.timer)
                    this.timer = null
                }
            },
            async applyCodeAndVerify(code) {
                if (window.__noyanOtpVerifyLock) {
                    return
                }

                await $wire.set('code', code)

                if (window.__noyanOtpVerifyLock) {
                    return
                }

                window.__noyanOtpVerifyLock = Date.now()
                await $wire.verify()
            },
            listenForWebOtp() {
                if ('OTPCredential' in window && navigator.credentials) {
                    this.abortController = new AbortController()
                    navigator.credentials.get({
                        otp: { transport: ['sms'] },
                        signal: this.abortController.signal
                    }).then(content => {
                        if (content && content.code) {
                            this.applyCodeAndVerify(content.code)
                        }
                    }).catch(() => {})
                }
            },
            destroy() {
                if (this.timer) clearInterval(this.timer)
                if (this.abortController) this.abortController.abort()
            }
        }">
            @if ($debugOtpCode && (app()->isLocal() || config('app.debug')))
                <div class="rounded-lg border border-dashed border-teal-500/50 bg-teal-50/50 p-3 text-center dark:bg-teal-950/20">
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                        {{ __('general.dev_otp_helper') }}:
                        <button
                            type="button"
                            class="font-mono font-bold text-teal-600 underline hover:text-teal-700 dark:text-teal-400"
                            x-on:click="applyCodeAndVerify('{{ $debugOtpCode }}')"
                        >
                            {{ $debugOtpCode }}
                        </button>
                    </flux:text>
                </div>
            @endif

            <div class="space-y-2 text-center">
                <flux:text>{{ __('general.otp_hint') }}</flux:text>
                <div class="flex items-center justify-center gap-2">
                    <flux:text class="font-medium tracking-wider" dir="ltr">{{ $this->maskedMobile }}</flux:text>
                    <flux:tooltip content="{{ __('general.edit') }}">
                        <flux:button
                            type="button"
                            size="xs"
                            variant="ghost"
                            icon="pencil"
                            icon:variant="outline"
                            wire:click="backToMobile"
                            x-on:click="window.__noyanOtpVerifyLock = false"
                        />
                    </flux:tooltip>
                </div>
            </div>

            <flux:otp
                wire:model.live="code"
                length="6"
                dir="ltr"
                label="{{ __('general.otp_code') }}"
                label:sr-only
                :error:icon="false"
                error:class="text-center"
                class="mx-auto"
                submit="auto"
            />

            <div class="space-y-3">
                <flux:button type="submit" variant="primary" color="teal" class="w-full">
                    {{ __('general.verify_otp') }}
                </flux:button>

                <flux:button
                    type="button"
                    variant="ghost"
                    class="w-full"
                    wire:click="resendCode"
                    x-on:click="window.__noyanOtpVerifyLock = false"
                    x-bind:disabled="remaining > 0"
                >
                    <span x-show="remaining > 0" x-cloak x-text="remaining + 's'"></span>
                    <span x-show="remaining <= 0">{{ __('general.otp_resend') }}</span>
                </flux:button>
            </div>
        </form>
    @endif
</div>
