<?php
namespace App\Application\Ports;

use DateTimeImmutable;

interface LendingPort
{
    public function transaction(callable $callback): mixed;
    public function lockMember(string $memberId): bool;
    /** @return list<string> */
    public function openDueDates(string $memberId): array;
    /** @return array<string,array{title:string,isbn:string,category_name:string}> */
    public function bookSnapshots(array $bookIds): array;
    public function reserveAvailableCopy(string $bookId): bool;
    public function storeLoan(string $loanId, string $memberId, string $actorId, DateTimeImmutable $at, array $items): void;
    public function lockLoanItem(string $loanId, string $itemId): ?array;
    public function markReturned(string $itemId, DateTimeImmutable $at): void;
    public function restockCopy(string $bookId): bool;
}
