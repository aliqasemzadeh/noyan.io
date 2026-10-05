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

    public string $prompt = '';

    public string $assistantReply = '';

    public bool $isTranscribing = false;

    /** @var TemporaryUploadedFile|null */
    public $audio = null;

    public function mount(string $context = 'accounting'): void
    {
        $this->context = $context;
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

            throw $exception;
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
            'prompt' => ['required', 'string', 'max:500'],
        ], attributes: [
            'prompt' => __('general.ai_prompt'),
        ]);

        $promptText = trim($this->prompt);

        try {
            $response = match ($this->context) {
                'users' => (new UserAssistant)->prompt($promptText),
                default => (new AccountingAssistant)->prompt($promptText),
            };
        } catch (\Throwable $exception) {
            report($exception);

            $this->assistantReply = '';
            Flux::toast(__('general.ai_error'), variant: 'danger');

            return;
        }

        $this->assistantReply = $response->text ?: __('general.ai_reply_empty');
        $this->reset(['prompt', 'audio']);

        if ($this->context === 'users') {
            $this->dispatch('panels.administrator.user.index.table');
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
        async pickAudio() {
            if (this.uploading || this.recording || $wire.isTranscribing) {
                return
            }

            this.uploading = true
            this.progress = 0

            try {
                await $wire.$upload('audio', {
                    accept: 'audio/*,.mp3,.wav,.webm,.ogg,.m4a',
                })
            } catch (error) {
                console.error(error)
            } finally {
                this.uploading = false
                this.progress = 0
            }
        },
        async toggle() {
            if (this.recording) {
                this.recorder?.stop()
                this.recording = false
                return
            }

            if (this.uploading || $wire.isTranscribing) {
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

                this.recorder.onstop = async () => {
                    stream.getTracks().forEach((track) => track.stop())
                    const type = this.mimeType.split(';')[0]
                    const blob = new Blob(this.chunks, { type })
                    const file = new File([blob], `voice.${this.extension}`, { type })

                    this.uploading = true
                    this.progress = 0

                    try {
                        await $wire.$upload('audio', file)
                    } catch (error) {
                        console.error(error)
                    } finally {
                        this.uploading = false
                        this.progress = 0
                    }
                }

                this.recorder.start()
                this.recording = true
            } catch (error) {
                console.error(error)
            }
        }
    }"
    class="space-y-3"
>
    <form wire:submit="sendPrompt">
        <flux:composer
            wire:model="prompt"
            :label="__('general.ai_prompt')"
            label:sr-only
            :placeholder="$this->placeholderText()"
            rows="2"
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
                        variant="subtle"
                        icon="paperclip"
                        x-on:click="pickAudio"
                        x-bind:disabled="uploading || recording || $wire.isTranscribing"
                    />
                </flux:tooltip>
            </x-slot>

            <x-slot name="actionsTrailing">
                <flux:tooltip content="{{ __('general.ai_voice_record') }}">
                    <flux:button
                        type="button"
                        size="sm"
                        variant="filled"
                        icon="mic"
                        x-on:click="toggle"
                        x-bind:aria-pressed="recording.toString()"
                        x-bind:disabled="uploading || $wire.isTranscribing"
                        x-bind:class="recording ? 'text-rose-600!' : ''"
                    />
                </flux:tooltip>

                <flux:button
                    type="submit"
                    size="sm"
                    variant="primary"
                    color="teal"
                    icon="send"
                    wire:loading.attr="disabled"
                    wire:target="sendPrompt"
                    x-bind:disabled="uploading || recording || $wire.isTranscribing"
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
