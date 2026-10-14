<?php
namespace App\Application\Ports;
use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
