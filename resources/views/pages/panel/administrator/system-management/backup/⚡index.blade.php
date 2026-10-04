<?php

use App\Services\System\SystemBackupService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    public bool $isRunning = false;

    public ?string $deletingPath = null;

    #[On('panels.administrator.system-management.backup.index.table')]
    public function refreshTable(): void
    {
        unset($this->backups);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{path: string, name: string, size: int, last_modified: \Illuminate\Support\Carbon}>
     */
    #[Computed]
    public function backups()
    {
        return app(SystemBackupService::class)->listLocalBackups();
    }

    public function runBackup(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;

        try {
            $service = app(SystemBackupService::class);
            $exitCode = $service->runDatabaseBackup();

            if ($exitCode === 0) {
                Flux::toast(__('general.backup_succeeded'));
            } else {
                Flux::toast(__('general.backup_failed'), variant: 'danger');
            }
        } finally {
            $this->isRunning = false;
            $this->dispatch('panels.administrator.system-management.backup.index.table');
        }
    }

    public function confirmDelete(string $path): void
    {
        $this->deletingPath = $path;
        Flux::modal('system.backup.delete')->show();
    }

    public function deleteBackup(): void
    {
        if ($this->deletingPath === null) {
            return;
        }

        try {
            app(SystemBackupService::class)->delete($this->deletingPath);
            Flux::toast(__('general.backup_deleted'));
            Flux::modals()->close();
            $this->deletingPath = null;
            $this->dispatch('panels.administrator.system-management.backup.index.table');
        } catch (\RuntimeException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function formatSize(int $bytes): string
    {
        return app(SystemBackupService::class)->formatBytes($bytes);
    }

    public function formatDate(\Illuminate\Support\Carbon $date): string
    {
        return Jalalian::fromDateTime($date)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.system_backups') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('system.dashboard')" wire:navigate>
                    {{ __('general.dashboard') }}
                </flux:breadcrumbs.item>
                <flux:breadcrumbs.item>
                    {{ __('general.system_management') }}
                </flux:breadcrumbs.item>
                <flux:breadcrumbs.item>
                    {{ __('general.system_backups') }}
                </flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="mt-4">
                <flux:heading size="xl" level="1">
                    {{ __('general.system_backups') }}
                </flux:heading>
                <flux:text class="mt-1">
                    {{ __('general.system_backups_hint') }}
                </flux:text>
            </div>
        </div>

        <flux:button
            variant="primary"
            color="teal"
            icon="hard-drive"
            wire:click="runBackup"
            wire:confirm="{{ __('general.backup_run_confirm') }}"
            :disabled="$isRunning"
        >
            {{ __('general.backup_run_now') }}
        </flux:button>
    </div>

    <flux:callout icon="database" variant="secondary" inline>
        {{ __('general.backup_schedule_hint') }}
    </flux:callout>

    <flux:card>
        @if ($this->backups->isEmpty())
            <flux:text>{{ __('general.backup_empty') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('general.backup_file') }}</flux:table.column>
                    <flux:table.column>{{ __('general.backup_size') }}</flux:table.column>
                    <flux:table.column>{{ __('general.backup_created_at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->backups as $backup)
                        <flux:table.row :key="$backup['path']">
                            <flux:table.cell>
                                <div class="font-mono text-sm" dir="ltr">{{ $backup['name'] }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->formatSize($backup['size']) }}</flux:table.cell>
                            <flux:table.cell>{{ $this->formatDate($backup['last_modified']) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="confirmDelete({{ \Illuminate\Support\Js::from($backup['path']) }})"
                                    />
                                </flux:tooltip>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:modal name="system.backup.delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('general.backup_delete_confirm') }}
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteBackup">
                    {{ __('general.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
