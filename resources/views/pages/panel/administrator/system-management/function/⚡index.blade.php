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

    public string $commandLine = '';

    public bool $isRunning = false;

    /**
     * @return array<string, array{label: string, icon: string, color: string}>
     */
    #[Computed]
    public function commands(): array
    {
        return app(SystemCommandRunner::class)->allowedCommands();
    }

    public function fillCommand(string $command): void
    {
        $this->commandLine = $command;
    }

    public function runTypedCommand(): void
    {
        $command = app(SystemCommandRunner::class)->normalizeCommand($this->commandLine);

        if ($command === '') {
            return;
        }

        $this->runCommand($command);
        $this->commandLine = '';
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
                Flux::toast(__('general.artisan_command_failed', ['command' => $command]), variant: 'danger');
            }
        } catch (DisallowedArtisanCommandException $exception) {
            $this->appendOutput($exception->getMessage().PHP_EOL);
            Flux::toast($exception->getMessage(), variant: 'danger');
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
                Flux::toast(__('general.system_update_failed'), variant: 'danger');
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
            <flux:breadcrumbs.item :href="route('system.dashboard')" wire:navigate>
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

        <div class="flex flex-wrap gap-2">
            @foreach ($this->commands as $command => $meta)
                <flux:button
                    size="sm"
                    variant="primary"
                    :color="$meta['color']"
                    :icon="$meta['icon']"
                    wire:click="fillCommand('{{ $command }}')"
                    :disabled="$isRunning"
                >
                    {{ __($meta['label']) }}
                </flux:button>
            @endforeach
        </div>
    </flux:card>

    <flux:card class="overflow-hidden p-0">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-800 bg-zinc-950 px-4 py-3">
            <div class="flex items-center gap-2">
                <flux:icon name="terminal" class="size-4 text-emerald-400" />
                <flux:heading size="md" class="text-zinc-100">{{ __('general.command_output') }}</flux:heading>
            </div>

            @if ($isRunning)
                <flux:badge color="amber" size="sm">{{ __('general.command_running') }}</flux:badge>
            @elseif ($output !== '')
                <flux:badge color="zinc" size="sm">{{ __('general.command_idle') }}</flux:badge>
            @endif
        </div>

        <pre
            wire:stream.replace="command-output"
            class="max-h-[28rem] min-h-56 overflow-auto bg-zinc-950 px-4 py-4 font-mono text-sm leading-relaxed text-emerald-300 whitespace-pre-wrap"
            dir="ltr"
        >{{ $output !== '' ? $output : __('general.command_output_placeholder') }}</pre>

        <form wire:submit="runTypedCommand" class="border-t border-zinc-800 bg-zinc-900 px-3 py-3" dir="ltr">
            <div class="flex items-center gap-2">
                <span class="shrink-0 font-mono text-sm text-emerald-400">$</span>
                <span class="hidden shrink-0 font-mono text-sm text-zinc-400 sm:inline">php artisan</span>
                <div class="min-w-0 flex-1">
                    <flux:input
                        wire:model="commandLine"
                        class="font-mono"
                        placeholder="{{ __('general.command_prompt') }}"
                        autocomplete="off"
                        :disabled="$isRunning"
                    />
                </div>
                <flux:button
                    type="submit"
                    variant="primary"
                    color="teal"
                    icon="play"
                    :disabled="$isRunning"
                >
                    {{ __('general.command_run') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
