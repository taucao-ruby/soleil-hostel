<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * User Role Enum - Single source of truth for RBAC
 * 
 * Backed enum for Laravel 11 native casting.
 * Values must match PostgreSQL ENUM type 'user_role_enum'.
 */
enum UserRole: string
{
    case USER = 'user';
    case MODERATOR = 'moderator';
    case ADMIN = 'admin';

    /**
     * Get all roles as an array of values (for validation rules & migrations).
     * 
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the default role for new users.
     */
    public static function default(): self
    {
        return self::USER;
    }
}
