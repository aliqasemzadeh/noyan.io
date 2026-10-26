<?php

use App\Enums\Business\BusinessPermission;
use App\Enums\Catalog\StockDocumentType;
use App\Models\Catalog\StockDocument;
use App\Support\Warehouse\Quantity;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public StockDocument $stockDocument;

    public function mount(StockDocument $stockDocument): void
    {
        Auth::user()?->ensureCurrentBusiness();
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockMovementView), 403);
        abort_unless((int) $stockDocument->business_id === (int) Auth::user()?->current_business_id, 404);
        abort_unless($stockDocument->type === StockDocumentType::Outbound, 404);

        $this->stockDocument = $stockDocument->load([
            'lines.product:id,name,sku,unit',
            'warehouse:id,name',
            'party:id,name,legal_name',
        ]);
    }
};
?>

<x-slot name="title">{{ __('general.pick_list') }} {{ $stockDocument->number }}</x-slot>

<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('general.pick_list') }}</flux:heading>
            <flux:text class="mt-1" dir="ltr">{{ $stockDocument->number }}</flux:text>
            <div class="mt-2 text-sm">
                {{ $stockDocument->warehouse?->name }}
                @if ($stockDocument->counterpartyLabel() !== '')
                    — {{ $stockDocument->counterpartyLabel() }}
                @endif
            </div>
        </div>
        <flux:button class="print:hidden" variant="primary" color="zinc" icon="printer" onclick="window.print()">
            {{ __('general.print_pick_list') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('general.sku') }}</flux:table.column>
            <flux:table.column>{{ __('general.product') }}</flux:table.column>
            <flux:table.column>{{ __('general.unit') }}</flux:table.column>
            <flux:table.column>{{ __('general.quantity') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($stockDocument->lines as $line)
                <flux:table.row wire:key="pick-{{ $line->id }}">
                    <flux:table.cell dir="ltr">{{ $line->product?->sku }}</flux:table.cell>
                    <flux:table.cell>{{ $line->product?->name }}</flux:table.cell>
                    <flux:table.cell>{{ $line->product?->unit?->label() }}</flux:table.cell>
                    <flux:table.cell dir="ltr">{{ Quantity::format((string) $line->quantity) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
