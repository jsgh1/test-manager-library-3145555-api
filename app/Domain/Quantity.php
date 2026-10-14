<?php
namespace App\Domain;
use InvalidArgumentException;
final readonly class Quantity {
    public function __construct(public int $value) {
        if ($value < 1) throw new InvalidArgumentException('Total copies must be positive.');
    }
}
