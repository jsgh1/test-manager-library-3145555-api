<?php
namespace App\Application\Services;

use App\Application\BusinessFailure;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserAccounts;

final readonly class LoginService
{
    public function __construct(
        private UserAccounts $users,
        private PasswordHasher $passwords,
        private TokenIssuer $tokens
    ) {}

    public function execute(string $username, string $password): array
    {
        $user = $this->users->byUsername(strtolower(trim($username)));
        if ($user === null || !$this->passwords->verify($password, $user['password_hash'])) {
            // Deliberately identical error for unknown user and incorrect password.
            throw new BusinessFailure('INVALID_CREDENTIALS', 'Invalid credentials.');
        }
        return [
            'access_token' => $this->tokens->issue($user['id'], $user['role']),
            'token_type' => 'Bearer',
            'expires_in' => 28800,
            'role' => $user['role'],
        ];
    }
}
