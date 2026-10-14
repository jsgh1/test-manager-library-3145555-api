<?php
namespace Tests;

use App\Application\BusinessFailure;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LendingPort;
use App\Application\Services\LendBooksService;
use App\Application\Services\ReturnItemService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FakeClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-14T14:00:00Z');
    }
}

final class FakeIds implements IdGenerator
{
    public int $current = 0;
    public function next(): string
    {
        return 'fixture-id-'.(++$this->current);
    }
}

final class FakeLendingPort implements LendingPort
{
    public bool $memberExists = true;
    public array $dueDates = [];
    public array $books = [
        'book-a' => ['title' => 'Old title', 'isbn' => '9780307474728', 'category_name' => 'Novela'],
        'book-b' => ['title' => 'Another book', 'isbn' => '9780000000001', 'category_name' => 'Historia'],
        'book-c' => ['title' => 'Third book', 'isbn' => '9780000000002', 'category_name' => 'Ciencia'],
    ];
    public array $copies = ['book-a' => 1, 'book-b' => 1, 'book-c' => 1];
    public array $stored = [];
    public ?array $item = null;
    public int $restocks = 0;

    public function transaction(callable $callback): mixed
    {
        $beforeCopies = $this->copies;
        $beforeStored = $this->stored;
        try {
            return $callback();
        } catch (\Throwable $failure) {
            $this->copies = $beforeCopies;
            $this->stored = $beforeStored;
            throw $failure;
        }
    }
    public function lockMember(string $memberId): bool { return $this->memberExists; }
    public function openDueDates(string $memberId): array { return $this->dueDates; }
    public function bookSnapshots(array $bookIds): array { return array_intersect_key($this->books, array_flip($bookIds)); }
    public function reserveAvailableCopy(string $bookId): bool
    {
        if (($this->copies[$bookId] ?? 0) < 1) return false;
        --$this->copies[$bookId];
        return true;
    }
    public function storeLoan(string $loanId, string $memberId, string $actorId, DateTimeImmutable $at, array $items): void
    {
        $this->stored = compact('loanId', 'memberId', 'actorId', 'at', 'items');
    }
    public function lockLoanItem(string $loanId, string $itemId): ?array { return $this->item; }
    public function markReturned(string $itemId, DateTimeImmutable $at): void { $this->item['returned_at'] = $at->format('c'); }
    public function restockCopy(string $bookId): bool { ++$this->restocks; ++$this->copies[$bookId]; return true; }
}

final class ApplicationLendingTest extends TestCase
{
    private function make(FakeLendingPort $fake): LendBooksService
    {
        return new LendBooksService($fake, new FakeClock(), new FakeIds());
    }

    public function test_snapshots_due_date_and_atomic_reservation(): void
    {
        $fake = new FakeLendingPort();
        $id = $this->make($fake)->execute('member-a', ['book-a'], 'operator-a');
        self::assertSame('fixture-id-1', $id);
        self::assertSame(0, $fake->copies['book-a']);
        self::assertSame('Old title', $fake->stored['items'][0]['book_title']);
        self::assertSame('2026-10-28', $fake->stored['items'][0]['due_at']->format('Y-m-d'));
    }

    public function test_rejects_duplicate_book_without_side_effect(): void
    {
        $fake = new FakeLendingPort();
        try {
            $this->make($fake)->execute('member-a', ['book-a', 'book-a'], 'operator-a');
            self::fail('Duplicate should have been rejected');
        } catch (BusinessFailure $exception) {
            self::assertSame('DUPLICATE_BOOK_IN_LOAN', $exception->errorCode);
            self::assertSame(1, $fake->copies['book-a']);
        }
    }

    public function test_rejects_member_limit_and_overdue(): void
    {
        $fake = new FakeLendingPort();
        $fake->dueDates = ['2026-10-30T00:00:00Z', '2026-10-30T00:00:00Z', '2026-10-30T00:00:00Z'];
        try {
            $this->make($fake)->execute('member-a', ['book-a'], 'operator-a');
            self::fail('Limit must be enforced');
        } catch (BusinessFailure $exception) {
            self::assertSame('MEMBER_LIMIT_EXCEEDED', $exception->errorCode);
        }
        $fake->dueDates = ['2026-10-13T00:00:00Z'];
        try {
            $this->make($fake)->execute('member-a', ['book-a'], 'operator-a');
            self::fail('Overdue borrowing must be rejected');
        } catch (BusinessFailure $exception) {
            self::assertSame('MEMBER_HAS_OVERDUE', $exception->errorCode);
        }
    }

    public function test_rolls_back_every_copy_on_partial_failure(): void
    {
        $fake = new FakeLendingPort();
        $fake->copies['book-b'] = 0;
        try {
            $this->make($fake)->execute('member-a', ['book-a', 'book-b'], 'operator-a');
            self::fail('Unavailable copies must be rejected');
        } catch (BusinessFailure $exception) {
            self::assertSame('BOOK_NOT_AVAILABLE', $exception->errorCode);
            self::assertSame(1, $fake->copies['book-a']);
            self::assertSame([], $fake->stored);
        }
    }

    public function test_return_is_idempotent_via_rejection(): void
    {
        $fake = new FakeLendingPort();
        $fake->copies['book-a'] = 0;
        $fake->item = [
            'id' => 'line-a', 'book_id' => 'book-a', 'book_title' => 'Old title',
            'isbn' => '9780307474728', 'category_name' => 'Novela',
            'due_at' => '2026-10-28T14:00:00Z', 'returned_at' => null,
        ];
        $service = new ReturnItemService($fake, new FakeClock());
        $returned = $service->execute('loan-a', 'line-a');
        self::assertSame('2026-10-14T14:00:00Z', $returned['returned_at']);
        self::assertSame(1, $fake->copies['book-a']);
        try {
            $service->execute('loan-a', 'line-a');
            self::fail('Second return must be rejected');
        } catch (BusinessFailure $exception) {
            self::assertSame('ITEM_ALREADY_RETURNED', $exception->errorCode);
            self::assertSame(1, $fake->restocks);
        }
    }
}
