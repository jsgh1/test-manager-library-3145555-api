<?php
namespace App\Adapters\Persistence;

use App\Application\Ports\MostBorrowedReader;
use Illuminate\Support\Facades\DB;

final class PostgresMostBorrowedReader implements MostBorrowedReader
{
    public function betweenDates(string $from, string $to): array
    {
        // Half-open interval includes the entire final UTC calendar day.
        $after = (new \DateTimeImmutable($to.'T00:00:00Z'))->modify('+1 day');
        $rows = DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.loaned_at', '>=', $from.'T00:00:00Z')
            ->where('l.loaned_at', '<', $after->format('Y-m-d\TH:i:s\Z'))
            ->select('li.book_id', 'li.book_title', 'li.category_name')
            ->selectRaw('COUNT(*) as times_borrowed')
            ->groupBy('li.book_id', 'li.book_title', 'li.category_name')
            ->orderByDesc('times_borrowed')->orderBy('li.book_title')->get();

        return $rows->map(fn ($row) => [
            'book_id' => $row->book_id,
            'book_title' => $row->book_title,
            'category_name' => $row->category_name,
            'times_borrowed' => (int)$row->times_borrowed,
        ])->all();
    }
}
