<?php
namespace App\Adapters\Persistence;

use App\Application\Ports\BookCatalog;
use Illuminate\Support\Facades\DB;

final class PostgresBookCatalog implements BookCatalog
{
    public function transaction(callable $callback): mixed { return DB::transaction($callback, 3); }
    public function categoryExists(string $categoryId): bool
    {
        return DB::table('library.category')->where('id', $categoryId)->exists();
    }
    public function activeIsbnExists(string $isbn): bool
    {
        return DB::table('library.book')->where('isbn', $isbn)->whereNull('deleted_at')->exists();
    }
    public function create(array $values): void { DB::table('library.book')->insert($values); }
    public function lockedActiveBook(string $id): ?array
    {
        $row = DB::table('library.book')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
        return $row ? (array)$row : null;
    }
    public function update(string $id, array $values): void
    {
        DB::table('library.book')->where('id', $id)->update($values);
    }
    public function hasOpenLoanItems(string $id): bool
    {
        return DB::table('library.loan_item')->where('book_id', $id)->whereNull('returned_at')->exists();
    }
}
