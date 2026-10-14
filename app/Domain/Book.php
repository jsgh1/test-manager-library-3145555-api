<?php
namespace App\Domain;
use DomainException;
final class Book {
    public function __construct(public int $totalCopies, public int $availableCopies) {
        if ($totalCopies < 1 || $availableCopies < 0 || $availableCopies > $totalCopies) throw new DomainException('Invalid copy count.');
    }
    public function withdraw(): void {
        if ($this->availableCopies === 0) throw new DomainException('No available copies.');
        --$this->availableCopies;
    }
    public function restock(): void {
        if ($this->availableCopies >= $this->totalCopies) throw new DomainException('Cannot exceed total copies.');
        ++$this->availableCopies;
    }
    public function changeTotal(int $newTotal): void {
        $nextAvailable = $this->availableCopies + ($newTotal - $this->totalCopies);
        if ($newTotal < 1 || $nextAvailable < 0 || $nextAvailable > $newTotal) throw new DomainException('Invalid total adjustment.');
        $this->totalCopies = $newTotal;
        $this->availableCopies = $nextAvailable;
    }
}
