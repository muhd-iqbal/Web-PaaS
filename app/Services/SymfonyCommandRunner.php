<?php

namespace App\Services;

use App\Contracts\CommandRunner;
use App\ValueObjects\CommandResult;
use Symfony\Component\Process\Process;

class SymfonyCommandRunner implements CommandRunner
{
    public function run(array $command, int $timeout): CommandResult
    {
        $process = new Process($command, base_path());
        $process->setTimeout($timeout);
        $process->run();

        return new CommandResult(
            exitCode: $process->getExitCode() ?? 1,
            output: trim($process->getOutput()),
            errorOutput: trim($process->getErrorOutput()),
        );
    }
}
