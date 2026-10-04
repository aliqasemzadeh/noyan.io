<?php

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;
use Spatie\Permission\Models\Role;

class RoleForm extends Form
{
    public ?Role $role = null;

    public string $name = '';

    public function setModel(Role $role): void
    {
        $this->role = $role;
        $this->name = $role->name;
    }

    /**
     * @return array<string, list<\Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('roles', 'name')->ignore($this->role?->id),
                Rule::notIn(['administrator']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.role_name'),
        ];
    }

    public function store(): Role
    {
        $validated = $this->validate();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $this->reset();

        return $role;
    }

    public function update(): void
    {
        $validated = $this->validate();

        if ($this->role === null || $this->role->name === 'administrator') {
            abort(403);
        }

        $this->role->update([
            'name' => $validated['name'],
        ]);

        $this->reset();
    }
}
