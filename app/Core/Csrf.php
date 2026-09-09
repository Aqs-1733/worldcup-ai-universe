<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_token'];
    }

    public static function verify(?string $token): bool
    {
        return is_string($token) && hash_equals(self::token(), $token);
    }

    public static function requireValid(): void
    {
        if (!self::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch.');
        }
    }
}
