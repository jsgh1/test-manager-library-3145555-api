<?php
namespace App\Support;
use RuntimeException;
final class ApiError extends RuntimeException {
    public function __construct(public readonly string $errorCode, public readonly int $httpStatus, string $message, public readonly array $details = []) {
        parent::__construct($message);
    }
    public static function invalid(string $field, string $issue): self {
        return new self('VALIDATION_ERROR',422,'Invalid request.',[['field'=>$field,'issue'=>$issue]]);
    }
}
