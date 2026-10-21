<?php

namespace App\Rules;

use App\Models\Link;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^'.Link::SLUG_PATTERN.'$/', $value)) {
            $fail('The :attribute must be 3-32 characters: letters, numbers, dashes and underscores only.');

            return;
        }

        if (Link::isReserved($value)) {
            $fail('The :attribute is reserved and cannot be used.');
        }
    }
}
