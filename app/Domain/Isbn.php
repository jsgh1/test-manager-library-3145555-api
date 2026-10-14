<?php
namespace App\Domain;
use InvalidArgumentException;
final readonly class Isbn {
    public function __construct(public string $value) {
        if (!preg_match('/^[0-9]{13}$/D', $value)) throw new InvalidArgumentException('ISBN must contain exactly 13 digits.');
    }
}
