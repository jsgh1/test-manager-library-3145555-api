<?php
namespace App\Application\Services;

use App\Application\BusinessFailure;
use App\Application\Ports\BookCatalog;
use App\Domain\Book;
use DomainException;

final readonly class UpdateBookService
{
    public function __construct(private BookCatalog $books) {}

    public function execute(string $id, array $input): void
    {
        if (!$this->books->categoryExists($input['category_id'])) {
            throw new BusinessFailure('VALIDATION_ERROR', 'Invalid category.');
        }
        $this->books->transaction(function () use ($id, $input): void {
            $current = $this->books->lockedActiveBook($id);
            if ($current === null) {
                throw new BusinessFailure('NOT_FOUND', 'Book not found.');
            }
            $book = new Book((int)$current['total_copies'], (int)$current['available_copies']);
            try {
                $book->changeTotal((int)$input['total_copies']);
            } catch (DomainException $error) {
                throw new BusinessFailure('VALIDATION_ERROR', 'Total copies cannot fall below checked-out copies.');
            }
            $this->books->update($id, [
                'title' => trim($input['title']),
                'author' => trim($input['author']),
                'category_id' => $input['category_id'],
                'total_copies' => $book->totalCopies,
                'available_copies' => $book->availableCopies,
            ]);
        });
    }
}
