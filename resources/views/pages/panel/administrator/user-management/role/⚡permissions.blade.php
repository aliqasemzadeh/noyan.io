<?php

use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        $this->redirect(route('system.roles.index'), navigate: true);
    }
};
?>

<div></div>
