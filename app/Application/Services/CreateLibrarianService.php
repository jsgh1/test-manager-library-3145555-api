<?php
namespace App\Application\Services;

use App\Application\BusinessFailure;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserAccounts;

final readonly class CreateLibrarianService
{
    public function __construct(
        private UserAccounts $users,
        private PasswordHasher $passwords,
        private IdGenerator $ids
    ) {}

    public function execute(string $username, string $password, string $role): array
    {
        if ($role !== 'librarian') {
            throw new BusinessFailure('VALIDATION_ERROR', 'Only librarians can be created through the API.');
        }
        $username = strtolower(trim($username));
        if ($username === '' || strlen($username) > 120 || strlen($password) < 8) {
            throw new BusinessFailure('VALIDATION_ERROR', 'Invalid username or password.');
        }
        if ($this->users->exists($username)) {
            throw new BusinessFailure('USERNAME_TAKEN', 'Username already exists.');
        }
        $id = $this->ids->next();
        $this->users->addLibrarian($id, $username, $this->passwords->hash($password));
        return ['id' => $id, 'username' => $username, 'role' => 'librarian'];
    }
}
