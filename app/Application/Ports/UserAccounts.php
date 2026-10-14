<?php
namespace App\Application\Ports;

interface UserAccounts
{
    /** @return array{id:string,username:string,role:string,password_hash:string}|null */
    public function byUsername(string $username): ?array;
    public function exists(string $username): bool;
    public function addLibrarian(string $id, string $username, string $passwordHash): void;
}
