<?php
namespace App\Adapters\Security;

use App\Application\Ports\PasswordHasher;

final class ArgonPasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        $hashed = password_hash($password, PASSWORD_ARGON2ID);
        if ($hashed === false) throw new \RuntimeException('Unable to hash password.');
        return $hashed;
    }

    public function verify(string $password, string $hashed): bool
    {
        return password_verify($password, $hashed);
    }
}
