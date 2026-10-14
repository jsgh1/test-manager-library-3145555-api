<?php
namespace App\Application\Ports;

interface MostBorrowedReader
{
    /** @return list<array{book_id:string,book_title:string,category_name:string,times_borrowed:int}> */
    public function betweenDates(string $from, string $to): array;
}
