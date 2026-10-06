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

    /** @var list<array{role: string, content: string}> */
    public array $messages = [];

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

        $this->messages[] = [
            'role' => 'user',
            'content' => $promptText,
        ];

        try {
            if ($this->context === 'users') {
                $response = (new UserAssistant)->prompt($promptText);
            } else {
                $user = auth()->user();

                if ($user === null) {
                    array_pop($this->messages);
                    Flux::toast(__('general.ai_error'), variant: 'danger');

                    return;
                }

                $response = (new AccountingAssistant)
                    ->continueOrStart($this->conversationId, $user)
                    ->prompt($promptText);
            }
        } catch (\Throwable $exception) {
            report($exception);

            array_pop($this->messages);
            $this->assistantReply = '';
            Flux::toast(__('general.ai_error'), variant: 'danger');

            return;
        }

        $reply = $response->text ?: __('general.ai_reply_empty');
        $this->assistantReply = $reply;
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $reply,
        ];
        $this->conversationId = $response->conversationId ?? $this->conversationId;
        $this->reset(['prompt', 'audio']);
        $this->isRecording = false;

        if ($this->context === 'users') {
            $this->dispatch('panels.administrator.user.index.table');
        }

        if ($this->context === 'accounting') {
            $this->dispatch('panels.accounting.account.index.table');
            $this->dispatch('panels.accounting.transaction.index.table');
        }

        Flux::toast(__('general.ai_prompt_sent'));
    }

    public function placeholderText(): string
    {
        return match ($this->context) {
            'users' => __('general.ai_prompt_placeholder'),
            default => __('general.accounting_ai_prompt_placeholder'),
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
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.messages
                if (el) {
                    el.scrollTop = el.scrollHeight
                }
            })
        },
    }"
    x-effect="syncRecording(isRecording)"
    x-init="$watch(() => $wire.messages, () => scrollToBottom())"
    class="flex h-full min-h-0 flex-col gap-3"
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

    <div
        x-ref="messages"
        class="min-h-0 flex-1 space-y-4 overflow-y-auto pe-1"
    >
        @forelse ($messages as $message)
            @if ($message['role'] === 'user')
                <div class="flex items-end justify-end gap-2">
                    <div class="max-w-[min(100%,42rem)] rounded-2xl rounded-ee-md bg-teal-600 px-4 py-2.5 text-sm leading-6 text-white shadow-sm whitespace-pre-wrap">
                        {{ $message['content'] }}
                    </div>
                    <div class="mb-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-teal-600 text-xs font-semibold text-white">
                        {{ mb_substr(__('general.ai_assistant_you'), 0, 1) }}
                    </div>
                </div>
            @else
                <div class="flex items-end justify-start gap-2">
                    <div class="mb-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-white text-teal-600 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-teal-300 dark:ring-zinc-700">
                        <flux:icon.sparkles variant="micro" class="size-4" />
                    </div>
                    <div class="max-w-[min(100%,42rem)] rounded-2xl rounded-es-md border border-zinc-200/80 bg-white px-4 py-2.5 text-sm leading-6 text-zinc-800 shadow-sm whitespace-pre-wrap dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        {{ $message['content'] }}
                    </div>
                </div>
            @endif
        @empty
            <div class="flex h-full min-h-48 flex-col items-center justify-center gap-4 px-4 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-teal-500/10 text-teal-600 ring-1 ring-teal-500/20 dark:text-teal-300">
                    <flux:icon.sparkles class="size-7" />
                </div>
                <div class="max-w-sm space-y-1">
                    <flux:heading size="base">{{ __('general.accounting_ai_title') }}</flux:heading>
                    <flux:text class="text-sm leading-6">{{ __('general.ai_assistant_empty') }}</flux:text>
                </div>
                <div class="flex flex-wrap justify-center gap-2">
                    <flux:badge size="sm" color="teal" icon="wallet">{{ __('general.accounting_ai_capability_balances') }}</flux:badge>
                    <flux:badge size="sm" color="sky" icon="banknotes">{{ __('general.transactions') }}</flux:badge>
                    <flux:badge size="sm" color="amber" icon="receipt">{{ __('general.accounting_ai_capability_invoices') }}</flux:badge>
                </div>
            </div>
        @endforelse

        <div
            wire:loading.flex
            wire:target="sendPrompt"
            class="hidden items-end justify-start gap-2"
        >
            <div class="mb-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-white text-teal-600 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-teal-300 dark:ring-zinc-700">
                <flux:icon.sparkles variant="micro" class="size-4 animate-pulse" />
            </div>
            <div class="rounded-2xl rounded-es-md border border-zinc-200/80 bg-white px-4 py-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-1.5">
                    <span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:0ms]"></span>
                    <span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:150ms]"></span>
                    <span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:300ms]"></span>
                    <flux:text class="ms-2 text-xs">{{ __('general.ai_assistant_thinking') }}</flux:text>
                </div>
            </div>
        </div>
    </div>

    <div
        x-show="uploading || $wire.isTranscribing"
        x-cloak
        class="shrink-0 space-y-1"
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

    <form wire:submit="sendPrompt" class="shrink-0 rounded-2xl border border-zinc-200/80 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:composer
            wire:model="prompt"
            rows="1"
            inline
            :label="__('general.ai_prompt')"
            label:sr-only
            :placeholder="$this->placeholderText()"
            submit="enter"
        >
            <x-slot name="actionsLeading">
                <flux:button
                    type="button"
                    size="sm"
                    variant="ghost"
                    icon="plus"
                    x-on:click="pickAudioFile()"
                    x-bind:disabled="uploading || recorderActive || isRecording || $wire.isTranscribing"
                />
            </x-slot>

            <x-slot name="actionsTrailing">
                <flux:button
                    type="button"
                    size="sm"
                    variant="filled"
                    icon="microphone"
                    x-bind:class="isRecording && 'text-rose-600!'"
                    x-on:click="isRecording = ! isRecording"
                    x-bind:disabled="uploading || $wire.isTranscribing"
                    :aria-label="__('general.ai_voice_record')"
                />

                <flux:button
                    type="submit"
                    size="sm"
                    variant="primary"
                    icon="paper-airplane"
                    class="rtl:[&_[data-flux-icon]]:rotate-180"
                    wire:loading.attr="disabled"
                    wire:target="sendPrompt"
                    x-bind:disabled="uploading || recorderActive || isRecording || $wire.isTranscribing"
                />
            </x-slot>
        </flux:composer>

        <flux:error name="prompt" />
        <flux:error name="audio" />
    </form>
</div>
