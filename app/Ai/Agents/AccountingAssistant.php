<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('gap')]
#[MaxSteps(2)]
#[Timeout(120)]
class AccountingAssistant implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'تو یک دستیار هوشمند حسابداری هستی. به فارسی و مختصر پاسخ بده.';
    }
}
