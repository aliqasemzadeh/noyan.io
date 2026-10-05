<?php

use App\Ai\Agents\UserAssistant;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Base64Audio;
use Laravel\Ai\Transcription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $prompt = '';

    public string $assistantReply = '';

    public string $search = '';

    public bool $isTranscribing = false;

    /** @var TemporaryUploadedFile|null */
    public $audio = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAudio(): void
    {
        if ($this->audio === null) {
            return;
        }

        $this->validate([
            'audio' => [
                'required',
                'file',
                'max:10240',
                'mimes:mp3,mpeg,wav,webm,ogg,oga,m4a,mp4,aac',
            ],
        ], attributes: [
            'audio' => __('general.ai_voice'),
        ]);

        $this->isTranscribing = true;

        try {
            $transcript = trim((string) Transcription::of(
                Base64Audio::fromUpload($this->audio, $this->resolveAudioMimeType($this->audio)),
            )
                ->language('fa')
                ->timeout(120)
                ->generate(Lab::Gemini, 'gemini-3.1-flash-lite'));
        } catch (\Throwable $exception) {
            report($exception);

            $this->isTranscribing = false;
            $this->reset('audio');

            Flux::toast(__('general.ai_voice_error'), variant: 'danger');

            return;
        }

        $this->isTranscribing = false;
        $this->reset('audio');

        if ($transcript === '') {
            Flux::toast(__('general.ai_voice_empty'), variant: 'warning');

            return;
        }

        $this->prompt = trim($this->prompt) !== ''
            ? trim($this->prompt)."\n".$transcript
            : $transcript;

        Flux::toast(__('general.ai_voice_ready'));
    }

    public function sendPrompt(): void
    {
        $this->validate([
            'prompt' => ['required', 'string', 'max:500'],
        ], attributes: [
            'prompt' => __('general.ai_prompt'),
        ]);

        $promptText = trim($this->prompt);

        try {
            $response = (new UserAssistant)->prompt($promptText);
        } catch (\Throwable $exception) {
            report($exception);

            $this->assistantReply = '';
            Flux::toast(__('general.ai_error'), variant: 'danger');

            return;
        }

        $this->assistantReply = $response->text ?: __('general.ai_reply_empty');
        $this->reset(['prompt', 'audio']);
        unset($this->users);
        $this->resetPage();

        Flux::toast(__('general.ai_prompt_sent'));
    }

    #[On('panels.administrator.user.index.table')]
    public function refreshTable(): void
    {
        unset($this->users);
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->search !== '', function ($query): void {
                $query->where('mobile', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->paginate(15);
    }

    public function formatCreatedAt(User $user): string
    {
        return Jalalian::fromDateTime($user->created_at)->format('Y/m/d H:i');
    }

    protected function resolveAudioMimeType(UploadedFile $file): string
    {
        $clientMime = strtolower((string) $file->getClientMimeType());

        if (in_array($clientMime, ['video/webm'], true)) {
            return 'audio/webm';
        }

        if (in_array($clientMime, ['video/mp4', 'audio/m4a', 'audio/x-m4a'], true)) {
            return 'audio/mp4';
        }

        if (
            $clientMime !== ''
            && $clientMime !== 'application/octet-stream'
            && str_starts_with($clientMime, 'audio/')
        ) {
            return $clientMime;
        }

        return match (strtolower($file->getClientOriginalExtension())) {
            'mp3', 'mpeg' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
            'ogg', 'oga' => 'audio/ogg',
            'm4a', 'mp4' => 'audio/mp4',
            'aac' => 'audio/aac',
            default => 'audio/webm',
        };
    }
};
?>

<x-slot name="title">{{ __('general.users') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.users') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.users') }}
            </flux:heading>

            @can('user_create')
                <flux:modal.trigger name="user.create">
                    <flux:button variant="primary" color="teal" icon="plus">
                        {{ __('general.create_user') }}
                    </flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        <flux:text class="mt-2">
            {{ __('general.users_ai_hint') }}
        </flux:text>
    </div>

    <flux:separator variant="subtle" />

    <flux:card>
        <form
            wire:submit="sendPrompt"
            x-data="{
                uploading: false,
                progress: 0,
                recording: false,
                recorder: null,
                chunks: [],
                mimeType: 'audio/webm',
                extension: 'webm',
                pickMime() {
                    const options = [
                        { mime: 'audio/webm;codecs=opus', ext: 'webm' },
                        { mime: 'audio/webm', ext: 'webm' },
                        { mime: 'audio/ogg', ext: 'ogg' },
                        { mime: 'audio/mp4', ext: 'm4a' },
                    ]

                    for (const option of options) {
                        if (window.MediaRecorder && MediaRecorder.isTypeSupported(option.mime)) {
                            this.mimeType = option.mime
                            this.extension = option.ext
                            return
                        }
                    }
                },
                async toggle() {
                    if (this.recording) {
                        this.recorder?.stop()
                        this.recording = false
                        return
                    }

                    try {
                        this.pickMime()
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                        this.chunks = []
                        this.recorder = new MediaRecorder(stream, { mimeType: this.mimeType })

                        this.recorder.ondataavailable = (event) => {
                            if (event.data.size > 0) {
                                this.chunks.push(event.data)
                            }
                        }

                        this.recorder.onstop = () => {
                            stream.getTracks().forEach((track) => track.stop())
                            const type = this.mimeType.split(';')[0]
                            const blob = new Blob(this.chunks, { type })
                            const file = new File([blob], `voice.${this.extension}`, { type })

                            this.uploading = true
                            this.progress = 0

                            $wire.upload(
                                'audio',
                                file,
                                () => {
                                    this.uploading = false
                                    this.progress = 0
                                },
                                () => {
                                    this.uploading = false
                                    this.progress = 0
                                },
                                (event) => {
                                    this.progress = event.detail.progress
                                },
                                () => {
                                    this.uploading = false
                                    this.progress = 0
                                },
                            )
                        }

                        this.recorder.start()
                        this.recording = true
                    } catch (error) {
                        console.error(error)
                    }
                }
            }"
        >
            <flux:composer
                wire:model="prompt"
                :label="__('general.ai_prompt')"
                :description="__('general.ai_voice_hint')"
                :placeholder="__('general.ai_prompt_placeholder')"
                rows="3"
            >
                <x-slot name="header">
                    <div
                        x-show="uploading || $wire.isTranscribing"
                        x-cloak
                        class="space-y-1 px-1 pb-2"
                    >
                        <flux:progress
                            x-bind:value="uploading ? progress : 100"
                            color="teal"
                            class="h-1.5"
                        />
                        <flux:text class="text-xs">
                            <span x-show="uploading">{{ __('general.ai_voice_uploading') }}</span>
                            <span x-show="! uploading && $wire.isTranscribing">{{ __('general.ai_voice_transcribing') }}</span>
                        </flux:text>
                    </div>
                </x-slot>

                <x-slot name="actionsLeading">
                    <input
                        type="file"
                        class="sr-only"
                        x-ref="audioInput"
                        accept="audio/*,.mp3,.wav,.webm,.ogg,.m4a"
                        wire:model="audio"
                        x-on:livewire-upload-start="uploading = true; progress = 0"
                        x-on:livewire-upload-finish="uploading = false; progress = 0"
                        x-on:livewire-upload-cancel="uploading = false; progress = 0"
                        x-on:livewire-upload-error="uploading = false; progress = 0"
                        x-on:livewire-upload-progress="progress = $event.detail.progress"
                    />

                    <flux:tooltip content="{{ __('general.ai_voice') }}">
                        <flux:button
                            type="button"
                            size="sm"
                            variant="subtle"
                            icon="paperclip"
                            x-on:click="$refs.audioInput.click()"
                            x-bind:disabled="uploading || recording || $wire.isTranscribing"
                        />
                    </flux:tooltip>

                    <flux:tooltip content="{{ __('general.ai_voice_record') }}">
                        <flux:button
                            type="button"
                            size="sm"
                            variant="subtle"
                            icon="mic"
                            x-on:click="toggle"
                            x-bind:aria-pressed="recording.toString()"
                            x-bind:disabled="uploading || $wire.isTranscribing"
                            x-bind:class="recording ? 'text-rose-600!' : ''"
                        />
                    </flux:tooltip>
                </x-slot>

                <x-slot name="actionsTrailing">
                    <flux:button
                        type="submit"
                        size="sm"
                        variant="primary"
                        color="teal"
                        icon="send"
                        wire:loading.attr="disabled"
                        wire:target="sendPrompt"
                        x-bind:disabled="uploading || recording || $wire.isTranscribing"
                    >
                        <span wire:loading.remove wire:target="sendPrompt">{{ __('general.send') }}</span>
                        <span wire:loading wire:target="sendPrompt">{{ __('general.ai_thinking') }}</span>
                    </flux:button>
                </x-slot>
            </flux:composer>

            <flux:error name="prompt" />
            <flux:error name="audio" />
        </form>

        @if ($assistantReply !== '')
            <flux:callout icon="sparkles" variant="secondary" class="mt-4" inline>
                {{ $assistantReply }}
            </flux:callout>
        @endif
    </flux:card>

    <flux:card>
        <div class="mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />
        </div>

        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>{{ $user->mobile }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($user) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @can('user_access')
                                    <flux:tooltip content="{{ __('general.access') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="teal"
                                            icon="key-round"
                                            icon:variant="outline"
                                            :href="route('system.users.access', $user)"
                                            wire:navigate
                                        />
                                    </flux:tooltip>
                                @endcan

                                @can('user_edit')
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button size="xs" variant="primary" color="blue" icon="pencil" icon:variant="outline" wire:click="$dispatch('panels.administrator.user.edit.assign-data', { user: {{ $user->id }} })" />
                                    </flux:tooltip>
                                @endcan

                                @can('user_delete')
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button size="xs" variant="danger" icon="trash" icon:variant="outline" wire:click="$dispatch('panels.administrator.user.delete.assign-data', { user: {{ $user->id }} })" />
                                    </flux:tooltip>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            {{ __('general.no_users') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:user.create :key="'user-create'" />
    <livewire:user.edit :key="'user-edit'" />
    <livewire:user.delete :key="'user-delete'" />
</div>
