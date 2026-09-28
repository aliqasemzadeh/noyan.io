<?php

use App\Jobs\Notification\User\SendUserOtpJob;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;
use Spatie\OneTimePasswords\Enums\ConsumeOneTimePasswordResult;

new
#[Title('Login')]
class extends Component
{
    public string $step = 'mobile';

    public string $mobile = '';

    public string $code = '';

    public ?int $resendAvailableAt = null;

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

        request()->session()->regenerate();

        Flux::toast(__('general.login_success'));

        return $this->redirectIntended(default: route('dashboard'), navigate: true);
    }

    public function resendCode(): void
    {
        if ($this->resendAvailableAt !== null && now()->getTimestamp() < $this->resendAvailableAt) {
            Flux::toast(__('general.otp_resend_wait'), variant: 'warning');

            return;
        }

        $this->sendCode();
    }

    public function backToMobile(): void
    {
        $this->step = 'mobile';
        $this->code = '';
        $this->resendAvailableAt = null;
        $this->resetErrorBag();
    }

    protected function hasActiveOneTimePassword(User $user): bool
    {
        return $user->oneTimePasswords()
            ->where('expires_at', '>', now())
            ->exists();
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-zinc-50 px-4 py-12 dark:bg-zinc-900">
    <div class="w-full max-w-md">
        <flux:card class="space-y-6">
            <div class="space-y-2 text-center">
                <flux:heading size="xl">{{ config('app.name') }}</flux:heading>
                <flux:heading size="lg">{{ __('general.login') }}</flux:heading>
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
                            autocomplete="tel"
                        />
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
                    init() {
                        this.tick()
                        this.timer = setInterval(() => this.tick(), 1000)
                    },
                    tick() {
                        const availableAt = $wire.resendAvailableAt ?? 0
                        this.remaining = Math.max(0, availableAt - Math.floor(Date.now() / 1000))
                        if (this.remaining === 0 && this.timer) {
                            clearInterval(this.timer)
                            this.timer = null
                        }
                    },
                    destroy() {
                        if (this.timer) clearInterval(this.timer)
                    }
                }">
                    <div class="space-y-2 text-center">
                        <flux:text>{{ __('general.otp_hint') }}</flux:text>
                        <flux:text class="font-medium">{{ $mobile }}</flux:text>
                    </div>

                    <flux:otp
                        wire:model="code"
                        length="6"
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

                        <flux:button type="button" variant="ghost" class="w-full" wire:click="backToMobile">
                            {{ __('general.change_mobile') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        </flux:card>
    </div>
</div>
