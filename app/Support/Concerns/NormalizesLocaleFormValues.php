<?php

namespace App\Support\Concerns;

use App\Support\LocaleDate;
use App\Support\Money;
use Closure;

trait NormalizesLocaleFormValues
{
    protected function normalizeMoneyFields(string ...$fields): void
    {
        foreach ($fields as $field) {
            if (! property_exists($this, $field)) {
                continue;
            }

            $value = $this->{$field};

            if (is_string($value) || $value === null) {
                $this->{$field} = Money::normalize($value);
            }
        }
    }

    protected function normalizeDateFields(string ...$fields): void
    {
        foreach ($fields as $field) {
            if (! property_exists($this, $field)) {
                continue;
            }

            $value = $this->{$field};

            if (! is_string($value) || $value === '') {
                continue;
            }

            $storage = LocaleDate::toStorageDate($value);

            if ($storage !== null) {
                $this->{$field} = $storage;
            }
        }
    }

    /**
     * @return list<string|Closure>
     */
    protected function localeDateRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (LocaleDate::parseFilterDate((string) $value) === null) {
                    $fail(__('validation.date', ['attribute' => $attribute]));
                }
            },
        ];
    }

    protected function todayInput(): string
    {
        return LocaleDate::formatInput(now());
    }
}
