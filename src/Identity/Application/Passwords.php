<?php
declare(strict_types=1);
namespace Ordely\Identity\Application;

final class Passwords
{
    public static function hash(#[\SensitiveParameter] string $password): string
    {
        // PASSWORD_DEFAULT is bcrypt on this pinned PHP runtime: reject silent truncation.
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new \InvalidArgumentException('Password must have 12-72 bytes and no NUL.');
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function email(string $email): string
    {
        $email = strtolower(trim($email));
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid email.');
        }
        return $email;
    }
}
