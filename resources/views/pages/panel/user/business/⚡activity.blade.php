<?php

use App\Models\Activity;
use App\Models\Business;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public Business $business;

    public string $search = '';

    public ?int $viewingActivityId = null;

    public function mount(Business $business): void
    {
        abort_unless(Auth::user()?->owns($business), 403);

        $this->business = $business;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function viewActivity(int $activityId): void
    {
        $exists = Activity::query()
            ->forBusiness($this->business)
            ->whereKey($activityId)
            ->exists();

        abort_unless($exists, 404);

        $this->viewingActivityId = $activityId;

        Flux::modal('business-activity-detail')->show();
    }

    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return Activity::query()
            ->forBusiness($this->business)
            ->with(['causer'])
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', $search)
                        ->orWhere('event', 'like', $search)
                        ->orWhere('subject_type', 'like', $search)
                        ->orWhereHasMorph('causer', [User::class], function ($query) use ($search): void {
                            $query->where('mobile', 'like', $search)
                                ->orWhere('first_name', 'like', $search)
                                ->orWhere('last_name', 'like', $search);
                        });
                });
            })
            ->latest('id')
            ->paginate(config('general.per_page', 15));
    }

    #[Computed]
    public function viewingActivity(): ?Activity
    {
        if ($this->viewingActivityId === null) {
            return null;
        }

        return Activity::query()
            ->forBusiness($this->business)
            ->with(['causer', 'subject'])
            ->whereKey($this->viewingActivityId)
            ->first();
    }

    public function formatCreatedAt(Activity $activity): string
    {
        return Jalalian::fromDateTime($activity->created_at)->format('Y/m/d H:i');
    }

    public function causerLabel(?User $causer): string
    {
        if ($causer === null) {
            return __('general.system_user');
        }

        return $causer->fullName() ?: $causer->mobile;
    }

    public function eventLabel(?string $event): string
    {
        if ($event === null || $event === '') {
            return '—';
        }

        $key = 'general.activity_event_'.$event;
        $translated = __($key);

        return $translated === $key ? $event : $translated;
    }

    public function subjectLabel(Activity $activity): string
    {
        $type = class_basename((string) $activity->subject_type);

        if ($activity->subject_id === null) {
            return $type !== '' ? $type : '—';
        }

        return $type.' #'.$activity->subject_id;
    }

    /**
     * @return list<array{attribute: string, old: mixed, new: mixed}>
     */
    public function changeRows(?Activity $activity): array
    {
        if ($activity === null) {
            return [];
        }

        $changes = $activity->attribute_changes;

        if ($changes === null || $changes->isEmpty()) {
            $properties = $activity->properties;

            if ($properties === null || $properties->isEmpty()) {
                return [];
            }

            return [
                [
                    'attribute' => 'properties',
                    'old' => null,
                    'new' => $properties->toArray(),
                ],
            ];
        }

        $old = collect($changes->get('old', []));
        $new = collect($changes->get('attributes', $changes->get('new', [])));
        $keys = $old->keys()->merge($new->keys())->unique()->values();

        return $keys->map(function (string $attribute) use ($old, $new): array {
            return [
                'attribute' => $attribute,
                'old' => $old->get($attribute),
                'new' => $new->get($attribute),
            ];
        })->all();
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        return (string) $value;
    }
};
?>

<x-slot name="title">{{ __('general.business_activity_log') }} - {{ $business->name }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.index')" wire:navigate>
                {{ __('general.my_businesses') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.view', $business)" wire:navigate>
                {{ $business->name }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.business_activity_log') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 space-y-1">
            <flux:heading size="xl" level="1">
                {{ __('general.business_activity_log') }}
            </flux:heading>
            <flux:text>{{ __('general.business_activity_log_hint') }}</flux:text>
        </div>
    </div>

    <flux:card>
        <div class="mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />
        </div>

        <flux:table :paginate="$this->activities">
            <flux:table.columns>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column>{{ __('general.activity_causer') }}</flux:table.column>
                <flux:table.column>{{ __('general.activity_event') }}</flux:table.column>
                <flux:table.column>{{ __('general.activity_subject') }}</flux:table.column>
                <flux:table.column>{{ __('general.activity_description') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->activities as $activity)
                    <flux:table.row :key="$activity->id">
                        <flux:table.cell>{{ $this->formatCreatedAt($activity) }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $this->causerLabel($activity->causer instanceof \App\Models\User ? $activity->causer : null) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $this->eventLabel($activity->event) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->subjectLabel($activity) }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="line-clamp-1">{{ $this->eventLabel($activity->description) }}</span>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:tooltip content="{{ __('general.activity_changes') }}">
                                <flux:button
                                    size="xs"
                                    variant="primary"
                                    color="teal"
                                    icon="eye"
                                    icon:variant="outline"
                                    wire:click="viewActivity({{ $activity->id }})"
                                />
                            </flux:tooltip>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('general.no_activity_logs') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="business-activity-detail" flyout position="right" class="min-w-full sm:min-w-md md:min-w-lg">
        @if ($this->viewingActivity)
            @php($detail = $this->viewingActivity)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('general.activity_changes') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $this->subjectLabel($detail) }} · {{ $this->eventLabel($detail->event) }}
                    </flux:text>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <flux:text class="text-zinc-500">{{ __('general.created_at') }}</flux:text>
                        <span>{{ $this->formatCreatedAt($detail) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <flux:text class="text-zinc-500">{{ __('general.activity_causer') }}</flux:text>
                        <span>{{ $this->causerLabel($detail->causer instanceof \App\Models\User ? $detail->causer : null) }}</span>
                    </div>
                </div>

                @php($rows = $this->changeRows($detail))
                @if ($rows === [])
                    <flux:text>{{ __('general.no_activity_logs') }}</flux:text>
                @else
                    <div class="space-y-3">
                        @foreach ($rows as $row)
                            <div wire:key="activity-change-{{ $detail->id }}-{{ $row['attribute'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="font-medium">{{ $row['attribute'] }}</div>
                                <div class="mt-2 grid gap-2 text-sm">
                                    <div>
                                        <flux:text class="text-zinc-500">{{ __('general.activity_old_value') }}</flux:text>
                                        <div class="mt-1 break-all font-mono text-xs" dir="ltr">{{ $this->formatValue($row['old']) }}</div>
                                    </div>
                                    <div>
                                        <flux:text class="text-zinc-500">{{ __('general.activity_new_value') }}</flux:text>
                                        <div class="mt-1 break-all font-mono text-xs" dir="ltr">{{ $this->formatValue($row['new']) }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>
