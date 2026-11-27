<?php

namespace App\Security;

use Illuminate\Contracts\Encryption\Encrypter;
use RuntimeException;

/**
 * Used in production when FIELD_ENCRYPTION_KEY is not set. The app can still
 * boot (so installs, builds and maintenance commands work), but any attempt to
 * read or write an encrypted field fails loudly instead of falling back to
 * a weaker key. Fail closed, not open.
 */
final class MissingFieldKeyEncrypter implements Encrypter
{
    private const MESSAGE = 'FIELD_ENCRYPTION_KEY is missing. Encrypted fields cannot be read or written in production.';

    public function encrypt(#[\SensitiveParameter] $value, $serialize = true): never
    {
        throw new RuntimeException(self::MESSAGE);
    }

    public function decrypt($payload, $unserialize = true): never
    {
        throw new RuntimeException(self::MESSAGE);
    }

    public function getKey(): never
    {
        throw new RuntimeException(self::MESSAGE);
    }

    /** @return array<string> */
    public function getAllKeys(): array
    {
        return [];
    }

    /** @return array<string> */
    public function getPreviousKeys(): array
    {
        return [];
    }
}
