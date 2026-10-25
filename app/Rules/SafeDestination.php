<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Extra safety checks for a redirect destination, on top of Laravel's
 * `url:http,https` rule.
 *
 * - no control characters or spaces (header-injection / parser tricks)
 * - no user:password@ part (classic phishing: https://bank.com@evil.com)
 * - not this app's own host (redirect loops)
 * - not localhost or a private/reserved IP address
 */
class SafeDestination implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a URL.');

            return;
        }

        if (preg_match('/[\x00-\x20\x7f]/', $value)) {
            $fail('The :attribute must not contain spaces or control characters.');

            return;
        }

        $parts = parse_url($value);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if ($host === '') {
            $fail('The :attribute must include a host.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('The :attribute must not contain a username or password.');

            return;
        }

        $own = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        $current = strtolower(request()->getHost());
        if ($host === $own || $host === $current) {
            $fail('The :attribute must not point back at this shortener.');

            return;
        }

        if ($this->isPrivateHost(rtrim($host, '.'))) {
            $fail('The :attribute must not point at localhost or a private network address.');
        }
    }

    private function isPrivateHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }
}
