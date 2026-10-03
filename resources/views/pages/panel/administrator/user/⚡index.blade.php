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
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new
#[Title('Users')]
class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $prompt = '';

    public string $assistantReply = '';

    public string $search = '';

    /** @var TemporaryUploadedFile|null */
    public $audio = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function removeAudio(): void
    {
        $this->reset('audio');
    }

    public function sendPrompt(): void
    {
        $this->validate([
            'prompt' => ['nullable', 'string', 'max:500', 'required_without:audio'],
            'audio' => [
                'nullable',
                'file',
                'max:10240',
                'required_without:prompt',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/webm,audio/ogg,audio/mp4,audio/x-m4a,audio/aac,video/webm,application/octet-stream',
            ],
        ], attributes: [
            'prompt' => __('general.ai_prompt'),
            'audio' => __('general.ai_voice'),
        ]);

        $promptText = trim($this->prompt);

        if ($this->audio !== null) {
            try {
                $transcript = trim((string) Transcription::of(
                    Base64Audio::fromUpload($this->audio, $this->resolveAudioMimeType($this->audio)),
                )
                    ->language('fa')
                    ->timeout(120)
                    ->generate(Lab::Gemini, 'gemini-3.1-flash-lite'));
            } catch (\Throwable $exception) {
                report($exception);

                Flux::toast(__('general.ai_voice_error'), variant: 'danger');

                return;
            }

            if ($transcript === '') {
                Flux::toast(__('general.ai_voice_empty'), variant: 'warning');

                return;
            }

            $promptText = $promptText !== ''
                ? $promptText."\n".$transcript
                : $transcript;

            $this->prompt = $promptText;
        }

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

        if ($clientMime === 'video/webm') {
            return 'audio/webm';
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
                {{ __('general.users') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.users') }}
            </flux:heading>

            <flux:modal.trigger name="user.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_user') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <flux:text class="mt-2">
            {{ __('general.users_ai_hint') }}
        </flux:text>
    </div>

    <flux:separator variant="subtle" />

    <flux:card>
        <form wire:submit="sendPrompt" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('general.ai_prompt') }}</flux:label>
                <flux:textarea
                    wire:model="prompt"
                    rows="3"
                    placeholder="{{ __('general.ai_prompt_placeholder') }}"
                />
                <flux:error name="prompt" />
            </flux:field>

            <div
                class="space-y-3"
                x-data="{
                    recording: false,
                    recorder: null,
                    chunks: [],
                    mimeType: 'audio/webm',
                    extension: 'webm',
                    pickMime() {
                        const options = [
                            { mime: 'audio/mp4', ext: 'm4a' },
                            { mime: 'audio/ogg', ext: 'ogg' },
                            { mime: 'audio/webm;codecs=opus', ext: 'webm' },
                            { mime: 'audio/webm', ext: 'webm' },
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
                                $wire.upload('audio', file)
                            }

                            this.recorder.start()
                            this.recording = true
                        } catch (error) {
                            console.error(error)
                        }
                    }
                }"
            >
                <flux:field>
                    <flux:label>{{ __('general.ai_voice') }}</flux:label>
                    <flux:input
                        type="file"
                        wire:model="audio"
                        accept="audio/*,.mp3,.wav,.webm,.ogg,.m4a"
                    />
                    <flux:description>{{ __('general.ai_voice_hint') }}</flux:description>
                    <flux:error name="audio" />
                </flux:field>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button
                        type="button"
                        variant="primary"
                        color="rose"
                        icon="mic"
                        x-on:click="toggle"
                        x-bind:aria-pressed="recording.toString()"
                    >
                        <span x-show="! recording">{{ __('general.ai_voice_record') }}</span>
                        <span x-show="recording" x-cloak>{{ __('general.ai_voice_stop') }}</span>
                    </flux:button>

                    @if ($audio)
                        <flux:badge color="teal" size="sm">{{ $audio->getClientOriginalName() }}</flux:badge>
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            wire:click="removeAudio"
                        >
                            {{ __('general.ai_voice_remove') }}
                        </flux:button>
                    @endif

                    <div wire:loading wire:target="audio">
                        <flux:text class="text-sm">{{ __('general.ai_voice_uploading') }}</flux:text>
                    </div>
                </div>
            </div>

            <flux:button
                type="submit"
                variant="primary"
                color="teal"
                class="w-full"
                icon="send"
                wire:loading.attr="disabled"
                wire:target="sendPrompt"
            >
                <span wire:loading.remove wire:target="sendPrompt">{{ __('general.send') }}</span>
                <span wire:loading wire:target="sendPrompt">{{ __('general.ai_thinking') }}</span>
            </flux:button>
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
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button size="xs" variant="primary" color="blue" icon="pencil" icon:variant="outline" wire:click="$dispatch('panels.administrator.user.edit.assign-data', { user: {{ $user->id }} })" />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button size="xs" variant="danger" icon="trash" icon:variant="outline" wire:click="$dispatch('panels.administrator.user.delete.assign-data', { user: {{ $user->id }} })" />
                                </flux:tooltip>
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
