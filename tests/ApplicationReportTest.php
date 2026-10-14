<?php
namespace Tests;

use App\Application\BusinessFailure;
use App\Application\Ports\MostBorrowedReader;
use App\Application\Services\MostBorrowedReportService;
use PHPUnit\Framework\TestCase;

final class FakeMostBorrowedReader implements MostBorrowedReader
{
    public array $filters = [];
    public function betweenDates(string $from, string $to): array
    {
        $this->filters = [$from, $to];
        return [['book_id' => 'book-1','book_title' => 'Frozen title',
            'category_name' => 'Novela','times_borrowed' => 2]];
    }
}

final class ApplicationReportTest extends TestCase
{
    public function test_report_uses_exact_requested_inclusive_dates_and_historical_values(): void
    {
        $fake = new FakeMostBorrowedReader();
        $response = (new MostBorrowedReportService($fake))->execute('2026-10-01','2026-10-14');
        self::assertSame(['2026-10-01','2026-10-14'], $fake->filters);
        self::assertSame('Frozen title', $response['rows'][0]['book_title']);
        self::assertSame('Novela', $response['rows'][0]['category_name']);
        self::assertSame(2, $response['rows'][0]['times_borrowed']);
    }

    public function test_inverted_range_is_rejected_before_port_call(): void
    {
        $fake = new FakeMostBorrowedReader();
        try {
            (new MostBorrowedReportService($fake))->execute('2026-10-14','2026-10-01');
            self::fail('Inverted range should have been rejected');
        } catch (BusinessFailure $error) {
            self::assertSame('INVALID_DATE_RANGE', $error->errorCode);
            self::assertSame([], $fake->filters);
        }
    }
}
