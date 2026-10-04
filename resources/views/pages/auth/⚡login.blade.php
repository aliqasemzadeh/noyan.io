<?php

use App\Jobs\Notification\User\SendUserOtpJob;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
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

    public function sendCode(): void
    {
        $this->validate([
            'mobile' => ['required', 'string', new IranianMobile(format: 'zero')],
        ], attributes: [
            'mobile' => __('general.mobile'),
        ]);

        $user = User::query()->firstOrCreate([
            'mobile' => $this->mobile,
        ]);

        if ($this->hasActiveOneTimePassword($user)) {
            $this->addError('mobile', __('general.otp_resend_wait'));
            Flux::toast(__('general.otp_resend_wait'), variant: 'warning');

            return;
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
        $this->validate([
            'mobile' => ['required', 'string', new IranianMobile(format: 'zero')],
            'code' => ['required', 'string', 'digits:6'],
        ], attributes: [
            'mobile' => __('general.mobile'),
            'code' => __('general.otp_code'),
        ]);

        $user = User::query()->where('mobile', $this->mobile)->first();

        if ($user === null) {
            $this->addError('code', __('general.otp_invalid'));
            Flux::toast(__('general.otp_invalid'), variant: 'danger');

            return null;
        }

        $result = $user->attemptLoginUsingOneTimePassword($this->code);

        if (! $result->isOk()) {
            $message = $result === ConsumeOneTimePasswordResult::IncorrectOneTimePassword
                || $result === ConsumeOneTimePasswordResult::NoOneTimePasswordsFound
                ? __('general.otp_invalid')
                : $result->validationMessage();

            $this->addError('code', $message);
            Flux::toast($message, variant: 'danger');

            return null;
        }

        session()->regenerate();

        $user->ensureCurrentBusiness();

        Flux::toast(__('general.login_success'));

        return $this->redirectIntended(default: route('dashboard'), navigate: true);
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

    #[Computed]
    public function maskedMobile(): string
    {
        if (strlen($this->mobile) < 7) {
            return $this->mobile;
        }

        return substr($this->mobile, 0, 4).'***'.substr($this->mobile, -4);
    }

    protected function hasActiveOneTimePassword(User $user): bool
    {
        return $user->oneTimePasswords()
            ->where('expires_at', '>', now())
            ->exists();
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
                <div dir="rtl">
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
                </div>
                <flux:error name="mobile" />
            </flux:field>

            <flux:button type="submit" variant="primary" color="teal" class="w-full">
                {{ __('general.send_otp') }}
            </flux:button>
        </form>
    @else
        <form wire:submit="verify" class="space-y-6" x-data="{
            remaining: Math.max(0, ($wire.resendAvailableAt ?? 0) - Math.floor(Date.now() / 1000)),
            timer: null,
            abortController: null,
            init() {
                this.tick()
                this.timer = setInterval(() => this.tick(), 1000)
                this.listenForWebOtp()
            },
            tick() {
                const availableAt = $wire.resendAvailableAt ?? 0
                this.remaining = Math.max(0, availableAt - Math.floor(Date.now() / 1000))
                if (this.remaining === 0 && this.timer) {
                    clearInterval(this.timer)
                    this.timer = null
                }
            },
            listenForWebOtp() {
                if ('OTPCredential' in window && navigator.credentials) {
                    this.abortController = new AbortController()
                    navigator.credentials.get({
                        otp: { transport: ['sms'] },
                        signal: this.abortController.signal
                    }).then(content => {
                        if (content && content.code) {
                            $wire.set('code', content.code)
                            $wire.verify()
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
                            x-on:click="$wire.set('code', '{{ $debugOtpCode }}'); $wire.verify()"
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
                        />
                    </flux:tooltip>
                </div>
            </div>

            <flux:otp
                wire:model="code"
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
                    x-bind:disabled="remaining > 0"
                >
                    <span x-show="remaining > 0" x-cloak x-text="remaining + 's'"></span>
                    <span x-show="remaining <= 0">{{ __('general.otp_resend') }}</span>
                </flux:button>
            </div>
        </form>
    @endif
</div>
