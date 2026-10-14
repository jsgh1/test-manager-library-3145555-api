<?php
namespace App\Adapters\Persistence;

use App\Application\Ports\UserAccounts;
use Illuminate\Support\Facades\DB;

final class PostgresUserAccounts implements UserAccounts
{
    public function byUsername(string $username): ?array
    {
        $user = DB::table('library.user')->where('username', $username)->first();
        return $user ? (array)$user : null;
    }

    public function exists(string $username): bool
    {
        return DB::table('library.user')->where('username', $username)->exists();
    }

    public function addLibrarian(string $id, string $username, string $passwordHash): void
    {
        DB::table('library.user')->insert([
            'id' => $id, 'username' => $username,
            'password_hash' => $passwordHash, 'role' => 'librarian',
        ]);
    }
}
