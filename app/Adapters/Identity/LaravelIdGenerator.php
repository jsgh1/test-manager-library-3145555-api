<?php
namespace App\Adapters\Identity;

use App\Application\Ports\IdGenerator;
use Illuminate\Support\Str;

final class LaravelIdGenerator implements IdGenerator
{
    public function next(): string
    {
        return (string)Str::uuid();
    }
}
