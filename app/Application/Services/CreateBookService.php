<?php
namespace App\Application\Services;

use App\Application\BusinessFailure;
use App\Application\Ports\BookCatalog;
use App\Application\Ports\IdGenerator;
use App\Domain\Isbn;
use App\Domain\Quantity;

final readonly class CreateBookService
{
    public function __construct(private BookCatalog $books, private IdGenerator $ids) {}

    public function execute(array $input): string
    {
        if (!$this->books->categoryExists($input['category_id'])) {
            throw new BusinessFailure('VALIDATION_ERROR', 'Invalid category.');
        }
        new Isbn($input['isbn']);
        $copies = new Quantity((int)$input['total_copies']);
        if ($this->books->activeIsbnExists($input['isbn'])) {
            throw new BusinessFailure('ISBN_TAKEN', 'ISBN already exists.');
        }
        $id = $this->ids->next();
        $this->books->create([
            'id' => $id,
            'title' => trim($input['title']),
            'author' => trim($input['author']),
            'isbn' => $input['isbn'],
            'category_id' => $input['category_id'],
            'total_copies' => $copies->value,
            'available_copies' => $copies->value,
            'cover_key' => null,
            'deleted_at' => null,
        ]);
        return $id;
    }
}
