<?php
namespace App\Application\Services;

use App\Application\BusinessFailure;
use App\Application\Ports\MostBorrowedReader;

final readonly class MostBorrowedReportService
{
    public function __construct(private MostBorrowedReader $reader) {}

    public function execute(string $from, string $to): array
    {
        if ($to < $from) {
            throw new BusinessFailure('INVALID_DATE_RANGE', 'End date must not be before start date.');
        }
        return [
            'from' => $from,
            'to' => $to,
            'rows' => $this->reader->betweenDates($from, $to),
        ];
    }
}
