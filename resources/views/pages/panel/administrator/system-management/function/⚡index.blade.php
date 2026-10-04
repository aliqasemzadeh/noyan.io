<?php

use App\Enums\System\UpdateMode;
use App\Exceptions\System\DisallowedArtisanCommandException;
use App\Services\System\ProjectUpdater;
use App\Services\System\SystemCommandRunner;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $output = '';

    public bool $isRunning = false;

    /**
     * @return array<string, array{label: string, icon: string, color: string}>
     */
    #[Computed]
    public function commands(): array
    {
        return app(SystemCommandRunner::class)->allowedCommands();
    }

    public function runCommand(string $command): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;
        $this->output = '';

        try {
            $exitCode = app(SystemCommandRunner::class)->run(
                $command,
                function (string $chunk): void {
                    $this->appendOutput($chunk);
                },
            );

            if ($exitCode === 0) {
                Flux::toast(__('general.artisan_command_succeeded', ['command' => $command]));
            } else {
                Flux::toast(
                    variant: 'danger',
                    text: __('general.artisan_command_failed', ['command' => $command]),
                );
            }
        } catch (DisallowedArtisanCommandException $exception) {
            $this->appendOutput($exception->getMessage().PHP_EOL);
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        } finally {
            $this->isRunning = false;
        }
    }

    public function quickUpdate(): void
    {
        $this->runUpdate(UpdateMode::Quick);
    }

    public function fullUpdate(): void
    {
        $this->runUpdate(UpdateMode::Full);
    }

    protected function runUpdate(UpdateMode $mode): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;
        $this->output = '';

        try {
            $exitCode = app(ProjectUpdater::class)->run(
                $mode,
                function (string $chunk): void {
                    $this->appendOutput($chunk);
                },
            );

            if ($exitCode === 0) {
                Flux::toast(__('general.system_update_finished'));
            } else {
                Flux::toast(variant: 'danger', text: __('general.system_update_failed'));
            }
        } finally {
            $this->isRunning = false;
        }
    }

    protected function appendOutput(string $chunk): void
    {
        $this->output .= $chunk;

        $this->stream(
            to: 'command-output',
            content: $this->output,
            replace: true,
        );
    }
};
?>

<x-slot name="title">{{ __('general.system_functions') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_functions') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.system_functions') }}
            </flux:heading>
            <flux:text class="mt-1">
                {{ __('general.system_functions_hint') }}
            </flux:text>
        </div>
    </div>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('general.system_updates') }}</flux:heading>

        <div class="flex flex-wrap gap-3">
            <flux:button
                variant="primary"
                color="teal"
                icon="zap"
                wire:click="quickUpdate"
                wire:confirm="{{ __('general.quick_update_confirm') }}"
                :disabled="$isRunning"
            >
                {{ __('general.quick_update') }}
            </flux:button>

            <flux:button
                variant="primary"
                color="orange"
                icon="rocket"
                wire:click="fullUpdate"
                wire:confirm="{{ __('general.full_update_confirm') }}"
                :disabled="$isRunning"
            >
                {{ __('general.full_update') }}
            </flux:button>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('general.maintenance_commands') }}</flux:heading>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($this->commands as $command => $meta)
                <flux:tooltip :content="$command">
                    <flux:button
                        variant="primary"
                        :color="$meta['color']"
                        :icon="$meta['icon']"
                        class="w-full justify-start"
                        wire:click="runCommand('{{ $command }}')"
                        :disabled="$isRunning"
                    >
                        {{ __($meta['label']) }}
                    </flux:button>
                </flux:tooltip>
            @endforeach
        </div>
    </flux:card>

    <flux:card>
        <div class="mb-3 flex items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('general.command_output') }}</flux:heading>

            @if ($isRunning)
                <flux:badge color="amber" size="sm">{{ __('general.command_running') }}</flux:badge>
            @elseif ($output !== '')
                <flux:badge color="zinc" size="sm">{{ __('general.command_idle') }}</flux:badge>
            @endif
        </div>

        <pre
            wire:stream.replace="command-output"
            class="max-h-[28rem] min-h-48 overflow-auto rounded-lg bg-zinc-950 p-4 text-sm leading-relaxed text-zinc-100 whitespace-pre-wrap"
            dir="ltr"
        >{{ $output !== '' ? $output : __('general.command_output_placeholder') }}</pre>
    </flux:card>
</div>
