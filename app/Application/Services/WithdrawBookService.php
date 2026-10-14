<?php
namespace App\Application\Services;

use App\Application\BusinessFailure;
use App\Application\Ports\BookCatalog;
use App\Application\Ports\Clock;

final readonly class WithdrawBookService
{
    public function __construct(private BookCatalog $books, private Clock $clock) {}

    public function execute(string $id): void
    {
        $this->books->transaction(function () use ($id): void {
            if ($this->books->lockedActiveBook($id) === null) {
                throw new BusinessFailure('NOT_FOUND', 'Book not found.');
            }
            if ($this->books->hasOpenLoanItems($id)) {
                throw new BusinessFailure('BOOK_HAS_OPEN_LOANS', 'Book has open loans.');
            }
            $this->books->update($id, [
                'deleted_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
            ]);
        });
    }
}
