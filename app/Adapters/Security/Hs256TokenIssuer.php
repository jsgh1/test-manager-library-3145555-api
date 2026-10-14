<?php
namespace App\Adapters\Security;

use App\Application\Ports\TokenIssuer;
use App\Support\Jwt;

final class Hs256TokenIssuer implements TokenIssuer
{
    public function issue(string $userId, string $role): string
    {
        return Jwt::issue($userId, $role);
    }
}
