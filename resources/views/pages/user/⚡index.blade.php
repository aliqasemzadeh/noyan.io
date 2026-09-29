<?php

use App\Ai\Agents\UserAssistant;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new
#[Title('Users')]
class extends Component
{
    use WithPagination;

    public string $prompt = '';

    public string $assistantReply = '';

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sendPrompt(): void
    {
        $this->validate([
            'prompt' => ['required', 'string', 'max:500'],
        ], attributes: [
            'prompt' => __('general.ai_prompt'),
        ]);

        try {
            $response = (new UserAssistant)->prompt($this->prompt);
        } catch (\Throwable $exception) {
            report($exception);

            $this->assistantReply = '';
            Flux::toast(__('general.ai_error'), variant: 'danger');

            return;
        }

        $this->assistantReply = $response->text ?: __('general.ai_reply_empty');
        $this->prompt = '';
        unset($this->users);
        $this->resetPage();

        Flux::toast(__('general.ai_prompt_sent'));
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

        <flux:heading size="xl" level="1" class="mt-4">
            {{ __('general.users') }}
        </flux:heading>

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

            <flux:button
                type="submit"
                variant="primary"
                color="teal"
                class="w-full"
                icon="paper-airplane"
                wire:loading.attr="disabled"
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
                icon="magnifying-glass"
                placeholder="{{ __('general.search') }}..."
                clearable
            />
        </div>

        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>{{ $user->mobile }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($user) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2">
                            {{ __('general.no_users') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
