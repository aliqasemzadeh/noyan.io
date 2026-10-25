<?php

use App\Models\Accounting\BusinessSettlementType;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[On('panels.accounting.settlement-type.set-default')]
    public function setDefault(int $id): void
    {
        $businessId = Auth::user()?->current_business_id;

        $row = BusinessSettlementType::query()
            ->where('business_id', $businessId)
            ->whereKey($id)
            ->firstOrFail();

        $row->setAsDefault();

        $this->dispatch('panels.accounting.settlement-type.index.table');

        Flux::toast(__('general.settlement_type_set_as_default'));
    }
};
?>

<div class="hidden"></div>
