<?php

namespace App\Contracts;

use App\ValueObjects\CommandResult;

interface CommandRunner
{
    /** @param list<string> $command */
    public function run(array $command, int $timeout): CommandResult;
}
