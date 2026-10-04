<?php

use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        $this->redirect(route('system.users.index'), navigate: true);
    }
};
?>

<div></div>
