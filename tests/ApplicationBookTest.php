<?php
namespace Tests;

use App\Application\BusinessFailure;
use App\Application\Ports\BookCatalog;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Services\CreateBookService;
use App\Application\Services\UpdateBookService;
use App\Application\Services\WithdrawBookService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FakeCatalog implements BookCatalog
{
    public bool $categoryExists = true;
    public bool $isbnTaken = false;
    public bool $hasOpenLoans = false;
    public array $saved = [];
    public function transaction(callable $callback): mixed { return $callback(); }
    public function categoryExists(string $id): bool { return $this->categoryExists; }
    public function activeIsbnExists(string $isbn): bool { return $this->isbnTaken; }
    public function create(array $values): void { $this->saved = $values; }
    public function lockedActiveBook(string $id): ?array { return $this->saved ?: null; }
    public function update(string $id, array $values): void { $this->saved = array_merge($this->saved, $values); }
    public function hasOpenLoanItems(string $id): bool { return $this->hasOpenLoans; }
}
final class FixedBookClock implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-14T14:00:00Z'); }
}
final class FixedBookIds implements IdGenerator
{
    public function next(): string { return 'book-test-id'; }
}

final class ApplicationBookTest extends TestCase
{
    private function create(FakeCatalog $books): void
    {
        (new CreateBookService($books, new FixedBookIds()))->execute([
            'title' => '  Title ', 'author' => ' Author ',
            'isbn' => '9780307474728', 'category_id' => 'novel',
            'total_copies' => 3,
        ]);
    }

    public function test_creating_book_starts_with_all_copies_available(): void
    {
        $books = new FakeCatalog();
        $this->create($books);
        self::assertSame(3, $books->saved['available_copies']);
        self::assertSame('Title', $books->saved['title']);
        self::assertNull($books->saved['deleted_at']);
    }

    public function test_duplicate_isbn_fails_before_storage(): void
    {
        $books = new FakeCatalog();
        $books->isbnTaken = true;
        try {
            $this->create($books);
            self::fail('Duplicate must fail');
        } catch (BusinessFailure $error) {
            self::assertSame('ISBN_TAKEN', $error->errorCode);
            self::assertSame([], $books->saved);
        }
    }

    public function test_updating_total_never_drops_below_checked_out_copies(): void
    {
        $books = new FakeCatalog();
        $this->create($books);
        $books->saved['available_copies'] = 1;
        $update = new UpdateBookService($books);
        try {
            $update->execute('book-test-id', [
                'title' => 'Renamed', 'author' => 'Writer',
                'category_id' => 'novel', 'total_copies' => 1,
            ]);
            self::fail('Cannot drop total under checked-out copies');
        } catch (BusinessFailure $error) {
            self::assertSame('VALIDATION_ERROR', $error->errorCode);
            self::assertSame(3, $books->saved['total_copies']);
        }
        $update->execute('book-test-id', [
            'title' => 'Renamed', 'author' => 'Writer',
            'category_id' => 'novel', 'total_copies' => 4,
        ]);
        self::assertSame(2, $books->saved['available_copies']);
        self::assertSame(4, $books->saved['total_copies']);
    }

    public function test_soft_delete_rejects_open_loans_and_never_erases_row(): void
    {
        $books = new FakeCatalog();
        $this->create($books);
        $delete = new WithdrawBookService($books, new FixedBookClock());
        $books->hasOpenLoans = true;
        try {
            $delete->execute('book-test-id');
            self::fail('Open loans must forbid deletion');
        } catch (BusinessFailure $error) {
            self::assertSame('BOOK_HAS_OPEN_LOANS', $error->errorCode);
            self::assertNull($books->saved['deleted_at']);
        }
        $books->hasOpenLoans = false;
        $delete->execute('book-test-id');
        self::assertNotNull($books->saved['deleted_at']);
        self::assertSame('Title', $books->saved['title']);
    }
}
