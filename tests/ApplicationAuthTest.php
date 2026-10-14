<?php
namespace Tests;

use App\Application\BusinessFailure;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserAccounts;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\LoginService;
use PHPUnit\Framework\TestCase;

final class FakeUserAccounts implements UserAccounts
{
    public array $accounts = [];

    public function byUsername(string $username): ?array
    {
        return $this->accounts[$username] ?? null;
    }

    public function exists(string $username): bool
    {
        return isset($this->accounts[$username]);
    }

    public function addLibrarian(string $id, string $username, string $passwordHash): void
    {
        $this->accounts[$username] = [
            'id' => $id, 'username' => $username, 'role' => 'librarian',
            'password_hash' => $passwordHash,
        ];
    }
}

final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return 'HASH:'.$password;
    }

    public function verify(string $password, string $hashed): bool
    {
        return hash_equals('HASH:'.$password, $hashed);
    }
}

final class FakeTokenIssuer implements TokenIssuer
{
    public function issue(string $userId, string $role): string
    {
        return 'SIGNED:'.$userId.':'.$role;
    }
}

final class FakeUserIds implements IdGenerator
{
    public function next(): string { return 'user-test-id'; }
}

final class ApplicationAuthTest extends TestCase
{
    public function test_creation_normalizes_username_and_hides_password_hash(): void
    {
        $users = new FakeUserAccounts();
        $create = new CreateLibrarianService($users, new FakePasswordHasher(), new FakeUserIds());
        $response = $create->execute('  Alice  ', 'secret_password', 'librarian');

        self::assertSame(['id' => 'user-test-id','username' => 'alice','role' => 'librarian'], $response);
        self::assertSame('HASH:secret_password', $users->accounts['alice']['password_hash']);
        self::assertArrayNotHasKey('password_hash', $response);
        self::assertArrayNotHasKey('password', $response);

        $login = new LoginService($users, new FakePasswordHasher(), new FakeTokenIssuer());
        $token = $login->execute('  ALICE ', 'secret_password');
        self::assertSame('SIGNED:user-test-id:librarian', $token['access_token']);
        self::assertSame(28800, $token['expires_in']);
    }

    public function test_user_name_conflict_is_reported(): void
    {
        $users = new FakeUserAccounts();
        $create = new CreateLibrarianService($users, new FakePasswordHasher(), new FakeUserIds());
        $create->execute('alice', 'secret_password', 'librarian');
        $this->expectException(BusinessFailure::class);
        $create->execute('ALICE', 'second_password', 'librarian');
    }

    public function test_administrator_cannot_be_created_through_application_use_case(): void
    {
        $create = new CreateLibrarianService(new FakeUserAccounts(), new FakePasswordHasher(), new FakeUserIds());
        try {
            $create->execute('another', 'secret_password', 'admin');
            self::fail('API administrator creation should be denied');
        } catch (BusinessFailure $e) {
            self::assertSame('VALIDATION_ERROR', $e->errorCode);
        }
    }

    public function test_bad_password_and_unknown_user_have_identical_errors(): void
    {
        $users = new FakeUserAccounts();
        $create = new CreateLibrarianService($users, new FakePasswordHasher(), new FakeUserIds());
        $create->execute('alice', 'secret_password', 'librarian');
        $login = new LoginService($users, new FakePasswordHasher(), new FakeTokenIssuer());

        foreach ([['alice', 'bad'], ['missing', 'secret_password']] as [$name, $password]) {
            try {
                $login->execute($name, $password);
                self::fail('Authentication must be denied');
            } catch (BusinessFailure $e) {
                self::assertSame('INVALID_CREDENTIALS', $e->errorCode);
                self::assertSame('Invalid credentials.', $e->getMessage());
            }
        }
    }
}
