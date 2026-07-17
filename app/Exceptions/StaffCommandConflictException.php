<?php

namespace App\Exceptions;

use RuntimeException;

class StaffCommandConflictException extends RuntimeException
{
    public function __construct(public readonly string $commandId)
    {
        parent::__construct('This idempotency key was already used for a different staff command.');
    }
}
