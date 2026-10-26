<?php

use App\Actions\Inventory\ConfirmInboundReceiptAction;
use App\Enums\Business\BusinessPermission;
use App\Models\Catalog\StockDocument;
use App\Support\Warehouse\Quantity;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?int $documentId = null;

    public string $number = '';

    /**
     * @var list<array{id: int, name: string, sku: string, quantity: string, received_quantity: string}>
     */
    public array $lines = [];

    #[On('panels.warehouse.receipt.confirm.open')]
    public function open(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockReceive), 403);

        $document = $this->findExpected($documentId);
        $this->documentId = $document->id;
        $this->number = $document->number;
        $this->lines = $document->lines->map(fn ($line): array => [
            'id' => $line->id,
            'name' => (string) ($line->product?->name ?? ''),
            'sku' => (string) ($line->product?->sku ?? ''),
            'quantity' => Quantity::format((string) $line->quantity),
            'received_quantity' => Quantity::format((string) $line->quantity),
        ])->all();

        Flux::modal('warehouse.receipt.confirm')->show();
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockReceive), 403);

        $document = $this->findExpected((int) $this->documentId);
        $received = [];

        foreach ($this->lines as $line) {
            $received[(int) $line['id']] = (string) $line['received_quantity'];
        }

        app(ConfirmInboundReceiptAction::class)->handle($document, Auth::user(), $received);

        $this->dispatch('panels.warehouse.documents.refresh');
        Flux::modals()->close();
        Flux::toast(__('general.receipt_confirmed'));
    }

    protected function findExpected(int $documentId): StockDocument
    {
        return StockDocument::query()
            ->with('lines.product')
            ->where('business_id', Auth::user()?->current_business_id)
            ->whereKey($documentId)
            ->firstOrFail();
    }
};
?>

<flux:modal name="warehouse.receipt.confirm" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.confirm_receipt') }}</flux:heading>
        <flux:text class="mt-1" dir="ltr">{{ $number }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-4">
        @foreach ($lines as $index => $line)
            <div wire:key="receipt-line-{{ $line['id'] }}" class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="font-medium">{{ $line['name'] }}</div>
                <div class="text-sm text-zinc-500" dir="ltr">{{ $line['sku'] }}</div>
                <div class="text-sm">{{ __('general.ordered_quantity') }}: <span dir="ltr">{{ $line['quantity'] }}</span></div>
                <flux:field>
                    <flux:label>{{ __('general.received_quantity') }}</flux:label>
                    <flux:input wire:model="lines.{{ $index }}.received_quantity" dir="ltr" />
                    <flux:error name="lines.{{ $index }}.received_quantity" />
                </flux:field>
            </div>
        @endforeach

        <flux:button type="submit" variant="primary" color="teal" class="w-full">{{ __('general.confirm_receipt') }}</flux:button>
    </form>
</flux:modal>
