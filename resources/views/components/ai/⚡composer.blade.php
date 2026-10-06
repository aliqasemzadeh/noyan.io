<?php

use App\Ai\Agents\AccountingAssistant;
use App\Ai\Agents\UserAssistant;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Base64Audio;
use Laravel\Ai\Transcription;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $context = 'accounting';

    #[Locked]
    public ?string $conversationId = null;

    public string $prompt = '';

    public string $assistantReply = '';

    public bool $isTranscribing = false;

    public bool $isRecording = false;

    /** @var TemporaryUploadedFile|null */
    public $audio = null;

    public function mount(string $context = 'accounting'): void
    {
        $this->context = $context;
    }

    public function notifyMicDenied(): void
    {
        $this->isRecording = false;

        Flux::toast(__('general.ai_voice_mic_denied'), variant: 'danger');
    }

    public function updatedAudio(): void
    {
        if ($this->audio === null) {
            return;
        }

        try {
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
        } catch (ValidationException $exception) {
            $this->reset('audio');

            Flux::toast($exception->validator->errors()->first('audio') ?: __('general.ai_voice_error'), variant: 'danger');

            return;
        }

        Flux::toast(__('general.ai_voice_uploaded'));

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
            'prompt' => ['required', 'string', 'max:2000'],
        ], attributes: [
            'prompt' => __('general.ai_prompt'),
        ]);

        $promptText = trim($this->prompt);

        try {
            if ($this->context === 'users') {
                $response = (new UserAssistant)->prompt($promptText);
            } else {
                $user = auth()->user();

                if ($user === null) {
                    Flux::toast(__('general.ai_error'), variant: 'danger');

                    return;
                }

                $response = (new AccountingAssistant)
                    ->continueOrStart($this->conversationId, $user)
                    ->prompt($promptText);
            }
        } catch (\Throwable $exception) {
            report($exception);

            $this->assistantReply = '';
            Flux::toast(__('general.ai_error'), variant: 'danger');

            return;
        }

        $this->assistantReply = $response->text ?: __('general.ai_reply_empty');
        $this->conversationId = $response->conversationId ?? $this->conversationId;
        $this->reset(['prompt', 'audio']);
        $this->isRecording = false;

        if ($this->context === 'users') {
            $this->dispatch('panels.administrator.user.index.table');
        }

        if ($this->context === 'accounting') {
            $this->dispatch('panels.accounting.account.index.table');
        }

        Flux::toast(__('general.ai_prompt_sent'));
    }

    public function placeholderText(): string
    {
        return match ($this->context) {
            'users' => __('general.ai_prompt_placeholder'),
            default => __('general.accounting_ai_prompt_1'),
        };
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

<div
    x-data="{
        uploading: false,
        progress: 0,
        recorder: null,
        mediaStream: null,
        chunks: [],
        mimeType: 'audio/webm',
        extension: 'webm',
        recorderActive: false,
        isRecording: @entangle('isRecording').live,
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
        pickAudioFile() {
            if (this.uploading || this.recorderActive || $wire.isTranscribing) {
                return
            }

            this.$refs.audioInput?.click()
        },
        queueAudioFile(file) {
            const input = this.$refs.audioInput

            if (! input || ! file) {
                return
            }

            const dataTransfer = new DataTransfer()
            dataTransfer.items.add(file)
            input.files = dataTransfer.files
            input.dispatchEvent(new Event('change', { bubbles: true }))
        },
        async startRecording() {
            if (this.recorderActive || this.uploading || $wire.isTranscribing) {
                return
            }

            try {
                this.pickMime()
                this.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true })

                if (! this.isRecording) {
                    this.mediaStream.getTracks().forEach((track) => track.stop())
                    this.mediaStream = null

                    return
                }

                this.chunks = []
                this.recorder = new MediaRecorder(this.mediaStream, { mimeType: this.mimeType })

                this.recorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        this.chunks.push(event.data)
                    }
                }

                this.recorder.onstop = () => {
                    this.mediaStream?.getTracks().forEach((track) => track.stop())
                    this.mediaStream = null
                    this.recorderActive = false

                    const type = this.mimeType.split(';')[0]
                    const blob = new Blob(this.chunks, { type })
                    const file = new File([blob], `voice.${this.extension}`, { type })

                    this.queueAudioFile(file)
                }

                this.recorder.start()
                this.recorderActive = true
            } catch (error) {
                console.error(error)
                this.isRecording = false
                $wire.notifyMicDenied()
            }
        },
        stopRecording() {
            if (! this.recorderActive || ! this.recorder) {
                return
            }

            this.recorder.stop()
        },
        syncRecording(enabled) {
            if (enabled) {
                this.startRecording()
            } else {
                this.stopRecording()
            }
        },
    }"
    x-effect="syncRecording(isRecording)"
    class="space-y-3"
>
    <input
        type="file"
        class="hidden"
        x-ref="audioInput"
        wire:model="audio"
        accept="audio/*,.mp3,.wav,.webm,.ogg,.m4a"
        x-on:livewire-upload-start="uploading = true; progress = 0"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
        x-on:livewire-upload-finish="uploading = false; progress = 0"
        x-on:livewire-upload-cancel="uploading = false; progress = 0"
        x-on:livewire-upload-error="uploading = false; progress = 0"
    />

    <form wire:submit="sendPrompt">
        <flux:composer
            wire:model="prompt"
            :label="__('general.ai_prompt')"
            label:sr-only
            :placeholder="$this->placeholderText()"
            rows="1"
            inline
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
                <flux:tooltip content="{{ __('general.ai_voice') }}">
                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="plus"
                        x-on:click="pickAudioFile()"
                        x-bind:disabled="uploading || recorderActive || isRecording || $wire.isTranscribing"
                    />
                </flux:tooltip>
            </x-slot>

            <x-slot name="actionsTrailing">
                <flux:toggle
                    wire:model.live="isRecording"
                    color="rose"
                    size="sm"
                    variant="filled"
                    :tooltip="__('general.ai_voice_record')"
                    x-bind:disabled="uploading || $wire.isTranscribing"
                >
                    <flux:icon icon="mic" variant="outline" class="size-4" />
                </flux:toggle>

                <flux:button
                    type="submit"
                    size="sm"
                    variant="primary"
                    color="teal"
                    icon="send"
                    wire:loading.attr="disabled"
                    wire:target="sendPrompt"
                    x-bind:disabled="uploading || recorderActive || isRecording || $wire.isTranscribing"
                />
            </x-slot>
        </flux:composer>

        <flux:error name="prompt" />
        <flux:error name="audio" />
    </form>

    @if ($assistantReply !== '')
        <flux:callout icon="sparkles" variant="secondary" inline>
            {{ $assistantReply }}
        </flux:callout>
    @endif
</div>
