<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Domain\Isbn;
use App\Domain\Quantity;
use App\Domain\Book;
use App\Domain\DateRange;
use App\Domain\Loan;
final class DomainTest extends TestCase {
    public function test_isbn_requires_thirteen_digits(): void {
        $this->expectException(InvalidArgumentException::class);
        new Isbn('123');
    }
    public function test_copy_count_never_below_zero(): void {
        $book = new Book(1,1);
        $book->withdraw();
        $this->expectException(DomainException::class);
        $book->withdraw();
    }
    public function test_copy_count_never_exceeds_total(): void {
        $book = new Book(1,1);
        $this->expectException(DomainException::class);
        $book->restock();
    }
    public function test_closed_loan_state_is_derived(): void {
        self::assertSame('closed', Loan::status([['due_at'=>'2020-01-01T00:00:00Z','returned_at'=>'2020-01-02T00:00:00Z']],new DateTimeImmutable('2026-10-14T00:00:00Z')));
    }
    public function test_overdue_state_is_derived(): void {
        self::assertSame('overdue', Loan::status([['due_at'=>'2020-01-01T00:00:00Z','returned_at'=>null]], new DateTimeImmutable('2026-10-14T00:00:00Z')));
    }
}
