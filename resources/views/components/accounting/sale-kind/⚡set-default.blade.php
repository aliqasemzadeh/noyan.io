<?php

use App\Models\Accounting\BusinessSaleKind;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[On('panels.accounting.sale-kind.set-default')]
    public function setDefault(int $id): void
    {
        $businessId = Auth::user()?->current_business_id;

        $row = BusinessSaleKind::query()
            ->where('business_id', $businessId)
            ->whereKey($id)
            ->firstOrFail();

        $row->setAsDefault();

        $this->dispatch('panels.accounting.sale-kind.index.table');

        Flux::toast(__('general.sale_kind_set_as_default'));
    }
};
?>

<div class="hidden"></div>
