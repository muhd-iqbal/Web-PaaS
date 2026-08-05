<?php

namespace App\Services;

use App\Contracts\CommandRunner;
use App\ValueObjects\CommandResult;
use Symfony\Component\Process\Process;

class SymfonyCommandRunner implements CommandRunner
{
    public function run(array $command, int $timeout): CommandResult
    {
        $dockerConfig = config('hosting.deployment.docker_config');

        if (! is_string($dockerConfig) || $dockerConfig === '') {
            throw new \RuntimeException('The Docker CLI configuration directory is not configured.');
        }

        if (! is_dir($dockerConfig) && ! mkdir($dockerConfig, 0700, true) && ! is_dir($dockerConfig)) {
            throw new \RuntimeException('The Docker CLI configuration directory could not be created.');
        }

        $process = new Process(
            $command,
            base_path(),
            ['DOCKER_CONFIG' => $dockerConfig],
        );
        $process->setTimeout($timeout);
        $process->run();

        return new CommandResult(
            exitCode: $process->getExitCode() ?? 1,
            output: trim($process->getOutput()),
            errorOutput: trim($process->getErrorOutput()),
        );
    }
}
