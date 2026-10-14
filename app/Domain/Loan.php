<?php
namespace App\Domain;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
final class Loan {
    public static function validateItems(array $bookIds): void {
        if (count($bookIds)<1 || count($bookIds)>3) throw new DomainException('A loan must contain one to three books.');
        if (count(array_unique($bookIds)) !== count($bookIds)) throw new DomainException('Loan books must be distinct.');
    }
    public static function dueAt(DateTimeImmutable $loanedAt): DateTimeImmutable {
        return $loanedAt->modify('+14 days');
    }
    public static function status(array $items, DateTimeImmutable $clock): string {
        $open = array_filter($items, fn($x)=>empty($x['returned_at']));
        if (!$open) return 'closed';
        foreach ($open as $item) if (new DateTimeImmutable($item['due_at']) < $clock) return 'overdue';
        return 'open';
    }
}
