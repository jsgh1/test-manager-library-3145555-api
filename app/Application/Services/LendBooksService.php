<?php
namespace App\Application\Services;

use App\Application\BusinessFailure;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LendingPort;
use App\Domain\Loan;
use DateTimeImmutable;

final readonly class LendBooksService
{
    public function __construct(
        private LendingPort $repository,
        private Clock $clock,
        private IdGenerator $ids
    ) {}

    /** @param list<string> $bookIds */
    public function execute(string $memberId, array $bookIds, string $actorId): string
    {
        if (count($bookIds) < 1 || count($bookIds) > 3) {
            throw new BusinessFailure('VALIDATION_ERROR', 'A loan must contain one to three books.');
        }
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new BusinessFailure('DUPLICATE_BOOK_IN_LOAN', 'A loan cannot repeat a book.');
        }

        return $this->repository->transaction(function () use ($memberId, $bookIds, $actorId): string {
            if (!$this->repository->lockMember($memberId)) {
                throw new BusinessFailure('NOT_FOUND', 'Member not found.');
            }

            $now = $this->clock->now();
            $dueDates = $this->repository->openDueDates($memberId);
            foreach ($dueDates as $dueAt) {
                if (new DateTimeImmutable($dueAt) < $now) {
                    throw new BusinessFailure('MEMBER_HAS_OVERDUE', 'Member has overdue books.');
                }
            }
            if (count($dueDates) + count($bookIds) > 3) {
                throw new BusinessFailure('MEMBER_LIMIT_EXCEEDED', 'Member already has too many borrowed books.', [
                    ['field' => 'items', 'issue' => 'active count: '.count($dueDates)]
                ]);
            }

            $snapshots = $this->repository->bookSnapshots($bookIds);
            foreach ($bookIds as $bookId) {
                if (!isset($snapshots[$bookId])) {
                    throw new BusinessFailure('NOT_FOUND', 'Book not found.');
                }
            }

            // Reserve every copy in one transaction: any conflict rolls all reservations back.
            foreach ($bookIds as $bookId) {
                if (!$this->repository->reserveAvailableCopy($bookId)) {
                    throw new BusinessFailure('BOOK_NOT_AVAILABLE', 'No copies are available.');
                }
            }

            $loanId = $this->ids->next();
            $due = Loan::dueAt($now);
            $items = [];
            foreach ($bookIds as $bookId) {
                $items[] = [
                    'id' => $this->ids->next(),
                    'book_id' => $bookId,
                    'book_title' => $snapshots[$bookId]['title'],
                    'isbn' => $snapshots[$bookId]['isbn'],
                    'category_name' => $snapshots[$bookId]['category_name'],
                    'due_at' => $due,
                    'returned_at' => null,
                ];
            }

            $this->repository->storeLoan($loanId, $memberId, $actorId, $now, $items);
            return $loanId;
        });
    }
}
