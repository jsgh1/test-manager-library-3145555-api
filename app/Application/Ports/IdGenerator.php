<?php
namespace App\Application\Ports;

interface IdGenerator
{
    public function next(): string;
}
