<?php
namespace App\Adapters\Persistence;

use App\Application\Ports\LendingPort;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PostgresLendingAdapter implements LendingPort
{
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback, 3);
    }

    public function lockMember(string $memberId): bool
    {
        return DB::table('library.member')->where('id', $memberId)->lockForUpdate()->exists();
    }

    public function openDueDates(string $memberId): array
    {
        return DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('li.returned_at')
            ->pluck('li.due_at')->all();
    }

    public function bookSnapshots(array $bookIds): array
    {
        return DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereIn('b.id', $bookIds)
            ->whereNull('b.deleted_at')
            ->get(['b.id', 'b.title', 'b.isbn', 'c.name as category_name'])
            ->mapWithKeys(fn ($b) => [$b->id => [
                'title' => $b->title,
                'isbn' => $b->isbn,
                'category_name' => $b->category_name,
            ]])->all();
    }

    public function reserveAvailableCopy(string $bookId): bool
    {
        return DB::table('library.book')->where('id', $bookId)
            ->whereNull('deleted_at')->where('available_copies', '>', 0)
            ->decrement('available_copies') === 1;
    }

    public function storeLoan(string $loanId, string $memberId, string $actorId, DateTimeImmutable $at, array $items): void
    {
        DB::table('library.loan')->insert([
            'id' => $loanId, 'member_id' => $memberId,
            'loaned_by_user_id' => $actorId, 'loaned_at' => $at->format('Y-m-d H:i:s.uP'),
        ]);
        foreach ($items as $item) {
            $item['loan_id'] = $loanId;
            $item['due_at'] = $item['due_at']->format('Y-m-d H:i:s.uP');
            DB::table('library.loan_item')->insert($item);
        }
    }

    public function lockLoanItem(string $loanId, string $itemId): ?array
    {
        $item = DB::table('library.loan_item')->where('id', $itemId)
            ->where('loan_id', $loanId)->lockForUpdate()->first();
        return $item ? (array)$item : null;
    }

    public function markReturned(string $itemId, DateTimeImmutable $at): void
    {
        DB::table('library.loan_item')->where('id', $itemId)
            ->update(['returned_at' => $at->format('Y-m-d H:i:s.uP')]);
    }

    public function restockCopy(string $bookId): bool
    {
        return DB::table('library.book')->where('id', $bookId)
            ->whereColumn('available_copies', '<', 'total_copies')
            ->increment('available_copies') === 1;
    }
}
