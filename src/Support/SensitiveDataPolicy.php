<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Support;

final class SensitiveDataPolicy
{
    private const REDACTED = '[REDACTED]';

    /**
     * @var array<int, string>
     */
    private const SENSITIVE_TOKENS = [
        'password',
        'passwd',
        'passphrase',
        'secret',
        'token',
        'tokens',
        'remember',
        'recovery',
        'otp',
    ];

    public static function isSensitive(string $key): bool
    {
        $normalized = strtolower(trim($key));
        if ($normalized === '') {
            return true;
        }

        $tokens = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        foreach ($tokens as $token) {
            if (in_array($token, self::SENSITIVE_TOKENS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    public static function redact(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $redacted;
    }

    public static function redactedValue(): string
    {
        return self::REDACTED;
    }
}
