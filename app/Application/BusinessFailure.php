<?php
namespace App\Application;

use RuntimeException;

final class BusinessFailure extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}
