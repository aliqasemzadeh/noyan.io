<?php

use App\Actions\Inventory\ConvertStockAction;
use App\Enums\Business\BusinessPermission;
use App\Enums\Catalog\StockConversionItemDirection;
use App\Enums\Catalog\StockConversionStatus;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockConversion;
use App\Models\Catalog\Warehouse;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public bool $showCreate = false;

    public ?int $warehouse_id = null;

    public ?int $project_id = null;

    public ?int $cost_center_id = null;

    public string $note = '';

    /** @var list<array{product_id: int|null, quantity: string}> */
    public array $inputs = [['product_id' => null, 'quantity' => '']];

    /** @var list<array{product_id: int|null, quantity: string}> */
    public array $outputs = [['product_id' => null, 'quantity' => '']];

    #[Url]
    public string $statusFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
        abort_unless(
            Auth::user()?->canInBusiness(BusinessPermission::StockConvert)
            || Auth::user()?->canInBusiness(BusinessPermission::StockMovementView),
            403
        );
    }

    /**
     * @return LengthAwarePaginator<int, StockConversion>
     */
    #[Computed]
    public function conversions(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return StockConversion::query()
            ->with(['warehouse', 'project', 'costCenter'])
            ->withCount('items')
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouses(): Collection
    {
        return Warehouse::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->where('track_inventory', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);
    }

    public function addInput(): void
    {
        $this->inputs[] = ['product_id' => null, 'quantity' => ''];
    }

    public function addOutput(): void
    {
        $this->outputs[] = ['product_id' => null, 'quantity' => ''];
    }

    public function createDraft(): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockConvert), 403);

        $this->validate([
            'warehouse_id' => ['required', 'integer'],
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.product_id' => ['required', 'integer'],
            'inputs.*.quantity' => ['required', 'numeric', 'gt:0'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.product_id' => ['required', 'integer'],
            'outputs.*.quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $businessId = (int) Auth::user()?->current_business_id;

        $conversion = DB::transaction(function () use ($businessId): StockConversion {
            $number = 'SC-'.now()->format('Ymd').'-'.str_pad((string) (StockConversion::query()->where('business_id', $businessId)->count() + 1), 4, '0', STR_PAD_LEFT);

            $conversion = StockConversion::query()->create([
                'business_id' => $businessId,
                'warehouse_id' => $this->warehouse_id,
                'project_id' => $this->project_id,
                'cost_center_id' => $this->cost_center_id,
                'conversion_number' => $number,
                'status' => StockConversionStatus::Draft,
                'note' => $this->note !== '' ? $this->note : null,
                'occurred_at' => now(),
                'created_by' => Auth::id(),
            ]);

            foreach ($this->inputs as $row) {
                $conversion->items()->create([
                    'product_id' => $row['product_id'],
                    'direction' => StockConversionItemDirection::Input,
                    'quantity' => $row['quantity'],
                ]);
            }

            foreach ($this->outputs as $row) {
                $conversion->items()->create([
                    'product_id' => $row['product_id'],
                    'direction' => StockConversionItemDirection::Output,
                    'quantity' => $row['quantity'],
                ]);
            }

            return $conversion;
        });

        $this->showCreate = false;
        $this->reset(['warehouse_id', 'project_id', 'cost_center_id', 'note']);
        $this->inputs = [['product_id' => null, 'quantity' => '']];
        $this->outputs = [['product_id' => null, 'quantity' => '']];
        unset($this->conversions);

        Flux::toast(__('general.stock_conversion_created', ['number' => $conversion->conversion_number]));
    }

    public function post(int $conversionId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockConvert), 403);

        $conversion = StockConversion::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->whereKey($conversionId)
            ->firstOrFail();

        app(ConvertStockAction::class)->handle($conversion, Auth::id());
        unset($this->conversions);
        Flux::toast(__('general.stock_conversion_posted'));
    }

    public function formatDate(StockConversion $conversion): string
    {
        return Jalalian::fromDateTime($conversion->occurred_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.stock_conversions') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('warehouse.dashboard')" wire:navigate>{{ __('general.warehouse') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('general.stock_conversions') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('general.stock_conversions') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.stock_conversions_hint') }}</flux:text>
            </div>
            @if (auth()->user()?->canInBusiness(BusinessPermission::StockConvert))
                <flux:button variant="primary" color="orange" icon="plus" wire:click="$set('showCreate', true)">
                    {{ __('general.create_stock_conversion') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($showCreate)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.create_stock_conversion') }}</flux:heading>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('general.warehouse') }}</flux:label>
                    <flux:select wire:model="warehouse_id" variant="listbox" searchable>
                        @foreach ($this->warehouses as $warehouse)
                            <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="warehouse_id" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('general.note') }}</flux:label>
                    <flux:input wire:model="note" />
                </flux:field>
            </div>

            <div class="space-y-2">
                <flux:heading size="sm">{{ __('general.stock_conversion_inputs') }}</flux:heading>
                @foreach ($inputs as $index => $row)
                    <div class="grid gap-2 md:grid-cols-3" wire:key="input-{{ $index }}">
                        <flux:select wire:model="inputs.{{ $index }}.product_id" variant="listbox" searchable>
                            @foreach ($this->products as $product)
                                <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="inputs.{{ $index }}.quantity" dir="ltr" placeholder="{{ __('general.quantity') }}" />
                    </div>
                @endforeach
                <flux:button size="sm" wire:click="addInput">{{ __('general.add_row') }}</flux:button>
            </div>

            <div class="space-y-2">
                <flux:heading size="sm">{{ __('general.stock_conversion_outputs') }}</flux:heading>
                @foreach ($outputs as $index => $row)
                    <div class="grid gap-2 md:grid-cols-3" wire:key="output-{{ $index }}">
                        <flux:select wire:model="outputs.{{ $index }}.product_id" variant="listbox" searchable>
                            @foreach ($this->products as $product)
                                <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="outputs.{{ $index }}.quantity" dir="ltr" placeholder="{{ __('general.quantity') }}" />
                    </div>
                @endforeach
                <flux:button size="sm" wire:click="addOutput">{{ __('general.add_row') }}</flux:button>
            </div>

            <div class="flex gap-2">
                <flux:button variant="primary" color="orange" wire:click="createDraft">{{ __('general.save') }}</flux:button>
                <flux:button variant="ghost" wire:click="$set('showCreate', false)">{{ __('general.cancel') }}</flux:button>
            </div>
        </flux:card>
    @endif

    <flux:card>
        <div class="mb-4">
            <flux:select wire:model.live="statusFilter" variant="listbox" class="max-w-xs">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                @foreach (StockConversionStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->conversions">
            <flux:table.columns>
                <flux:table.column>{{ __('general.number') }}</flux:table.column>
                <flux:table.column>{{ __('general.warehouse') }}</flux:table.column>
                <flux:table.column>{{ __('general.status') }}</flux:table.column>
                <flux:table.column>{{ __('general.date') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->conversions as $conversion)
                    <flux:table.row wire:key="conversion-{{ $conversion->id }}">
                        <flux:table.cell dir="ltr">{{ $conversion->conversion_number }}</flux:table.cell>
                        <flux:table.cell>{{ $conversion->warehouse?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $conversion->status->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatDate($conversion) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($conversion->status === StockConversionStatus::Draft && auth()->user()?->canInBusiness(BusinessPermission::StockConvert))
                                <flux:button size="xs" variant="primary" color="teal" wire:click="post({{ $conversion->id }})" wire:confirm="{{ __('general.are_you_sure') }}">
                                    {{ __('general.post_conversion') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('general.no_stock_conversions') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
