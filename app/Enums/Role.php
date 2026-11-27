<?php

namespace App\Enums;

/**
 * The four roles from CLAUDE.md §2. Every person has exactly one.
 */
enum Role: string
{
    case Owner = 'owner';
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case User = 'user';

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::SuperAdmin => 3,
            self::Admin => 2,
            self::User => 1,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /** Owner and Super Admin see every entity and every record. */
    public function isLeadership(): bool
    {
        return $this === self::Owner || $this === self::SuperAdmin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::User => 'User',
        };
    }
}
