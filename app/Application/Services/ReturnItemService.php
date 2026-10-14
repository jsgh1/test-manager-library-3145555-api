<?php
namespace App\Application\Services;

use App\Application\BusinessFailure;
use App\Application\Ports\Clock;
use App\Application\Ports\LendingPort;
use DateTimeImmutable;

final readonly class ReturnItemService
{
    public function __construct(
        private LendingPort $repository,
        private Clock $clock
    ) {}

    public function execute(string $loanId, string $itemId): array
    {
        return $this->repository->transaction(function () use ($loanId, $itemId): array {
            $line = $this->repository->lockLoanItem($loanId, $itemId);
            if ($line === null) {
                throw new BusinessFailure('NOT_FOUND', 'Loan item not found.');
            }
            if ($line['returned_at'] !== null) {
                throw new BusinessFailure('ITEM_ALREADY_RETURNED', 'Item was already returned.');
            }
            $now = $this->clock->now();
            $this->repository->markReturned($itemId, $now);
            if (!$this->repository->restockCopy($line['book_id'])) {
                throw new BusinessFailure('STOCK_CONFLICT', 'Cannot exceed the total copies.');
            }
            return [
                'id' => $line['id'],
                'book_id' => $line['book_id'],
                'book_title' => $line['book_title'],
                'isbn' => trim($line['isbn']),
                'category_name' => $line['category_name'],
                'due_at' => (new DateTimeImmutable($line['due_at']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                'returned_at' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            ];
        });
    }
}
