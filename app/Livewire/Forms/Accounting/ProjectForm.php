<?php

namespace App\Livewire\Forms\Accounting;

use App\Models\Accounting\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ProjectForm extends Form
{
    public ?Project $project = null;

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public function setModel(Project $project): void
    {
        $this->project = $project;
        $this->code = $project->code ?? '';
        $this->name = $project->name;
        $this->is_active = $project->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'code' => __('general.code'),
            'name' => __('general.name'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): Project
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['business_id'] = $businessId;
        $validated['code'] = filled($validated['code'] ?? null) ? $validated['code'] : null;

        $project = Project::query()->create($validated);
        Project::forgetOptionsCache($businessId);
        $this->resetFormState();

        return $project;
    }

    public function update(): void
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['code'] = filled($validated['code'] ?? null) ? $validated['code'] : null;

        $this->project?->update($validated);
        Project::forgetOptionsCache($businessId);
        $this->resetFormState();
    }

    protected function requireBusinessId(): int
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'name' => [__('general.business_required')],
            ]);
        }

        return (int) $businessId;
    }

    protected function resetFormState(): void
    {
        $this->reset();
        $this->is_active = true;
    }
}
