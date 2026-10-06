<?php

use Livewire\Component;

new class extends Component
{
    /**
     * @return list<string>
     */
    public function prompts(): array
    {
        return [
            __('general.accounting_ai_prompt_1'),
            __('general.accounting_ai_prompt_2'),
            __('general.accounting_ai_prompt_3'),
            __('general.accounting_ai_prompt_4'),
        ];
    }

    /**
     * @return list<array{label: string, icon: string, color: string}>
     */
    public function capabilities(): array
    {
        return [
            [
                'label' => __('general.accounting_ai_capability_invoices'),
                'icon' => 'receipt',
                'color' => 'teal',
            ],
            [
                'label' => __('general.accounting_ai_capability_cheques'),
                'icon' => 'text-search',
                'color' => 'amber',
            ],
            [
                'label' => __('general.accounting_ai_capability_balances'),
                'icon' => 'wallet',
                'color' => 'sky',
            ],
            [
                'label' => __('general.accounting_ai_capability_expenses'),
                'icon' => 'banknote',
                'color' => 'rose',
            ],
            [
                'label' => __('general.accounting_ai_capability_parties'),
                'icon' => 'bot',
                'color' => 'violet',
            ],
        ];
    }
};
?>

<div
    x-data="{
        prompts: @js($this->prompts()),
        displayed: '',
        index: 0,
        char: 0,
        deleting: false,
        ready: false,
        timer: null,
        start() {
            this.ready = true
            this.tick()
        },
        tick() {
            const full = this.prompts[this.index] ?? ''

            if (! this.deleting) {
                this.displayed = full.slice(0, this.char + 1)
                this.char++

                if (this.char >= full.length) {
                    this.deleting = true
                    this.timer = setTimeout(() => this.tick(), 1600)
                    return
                }

                this.timer = setTimeout(() => this.tick(), 55)
                return
            }

            this.displayed = full.slice(0, this.char - 1)
            this.char--

            if (this.char <= 0) {
                this.deleting = false
                this.index = (this.index + 1) % this.prompts.length
                this.timer = setTimeout(() => this.tick(), 420)
                return
            }

            this.timer = setTimeout(() => this.tick(), 28)
        }
    }"
    x-init="start()"
    x-on:livewire:navigating.window="clearTimeout(timer)"
    class="opacity-0 translate-y-2 transition duration-700 ease-out"
    x-bind:class="ready && 'opacity-100 translate-y-0'"
>
    <flux:card class="overflow-hidden border-teal-200/70 bg-gradient-to-br from-teal-50/80 via-white to-sky-50/60 dark:border-teal-900/50 dark:from-teal-950/40 dark:via-zinc-900 dark:to-sky-950/30">
        <div class="flex flex-col gap-5">
            <div class="flex items-start gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 ring-1 ring-teal-500/20 dark:bg-teal-400/10 dark:text-teal-300">
                    <flux:icon.sparkles class="size-6 animate-pulse" />
                </div>

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg" level="2">
                        {{ __('general.accounting_ai_title') }}
                    </flux:heading>

                    <flux:text class="mt-2 text-base leading-7">
                        {{ __('general.accounting_ai_description') }}
                    </flux:text>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ($this->capabilities() as $i => $capability)
                    <div
                        class="opacity-0 translate-y-1 transition duration-500 ease-out"
                        x-bind:class="ready && 'opacity-100 translate-y-0'"
                        x-bind:style="'transition-delay: {{ 120 + ($i * 90) }}ms'"
                    >
                        <flux:badge size="sm" :color="$capability['color']" :icon="$capability['icon']">
                            {{ $capability['label'] }}
                        </flux:badge>
                    </div>
                @endforeach
            </div>

            <flux:separator variant="subtle" />

            <div class="rounded-xl border border-zinc-200/80 bg-white/80 px-4 py-3 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-950/40">
                <flux:text class="mb-2 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                    {{ __('general.accounting_ai_try_saying') }}
                </flux:text>

                <div class="flex min-h-8 items-center gap-2 font-medium text-zinc-800 dark:text-zinc-100">
                    <flux:icon.sparkles class="size-4 shrink-0 text-teal-500" />
                    <span x-text="displayed" class="break-words"></span>
                    <span class="inline-block h-5 w-0.5 animate-pulse bg-teal-500 align-middle" aria-hidden="true"></span>
                </div>
            </div>

            <flux:callout icon="sparkles" variant="secondary" inline>
                {{ __('general.accounting_ai_footer') }}
            </flux:callout>
        </div>
    </flux:card>
</div>
