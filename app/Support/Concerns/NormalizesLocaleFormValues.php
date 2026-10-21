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

    protected function localeDateAfterOrEqual(string $otherField): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($otherField): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! property_exists($this, $otherField)) {
                return;
            }

            $other = $this->{$otherField};

            if (! is_string($other) || $other === '') {
                return;
            }

            $left = LocaleDate::parseFilterDate((string) $value);
            $right = LocaleDate::parseFilterDate($other);

            if ($left === null || $right === null) {
                return;
            }

            if ($left->lt($right)) {
                $fail(__('validation.after_or_equal', [
                    'attribute' => $attribute,
                    'date' => $otherField,
                ]));
            }
        };
    }

    protected function todayInput(): string
    {
        return LocaleDate::formatInput(now());
    }
}
