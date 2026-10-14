<?php
namespace App\Application\Ports;

interface BookCatalog
{
    public function transaction(callable $callback): mixed;
    public function categoryExists(string $categoryId): bool;
    public function activeIsbnExists(string $isbn): bool;
    public function create(array $values): void;
    public function lockedActiveBook(string $id): ?array;
    public function update(string $id, array $values): void;
    public function hasOpenLoanItems(string $id): bool;
}
