<?php

namespace Tests\Fakes;

use App\Contracts\CommandRunner;
use App\ValueObjects\CommandResult;

class RecordingCommandRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    public function run(array $command, int $timeout): CommandResult
    {
        $this->commands[] = $command;
        $joined = implode(' ', $command);

        if (str_contains($joined, ' image inspect ')) {
            return new CommandResult(0, 'image-id');
        }

        if (str_contains($joined, ' network inspect ')) {
            return new CommandResult(1, errorOutput: 'not found');
        }

        if (str_contains($joined, '{{json .NetworkSettings.Networks}}')) {
            return new CommandResult(0, '{}');
        }

        if (str_contains($joined, ' container inspect hosting-project-') && ! str_contains($joined, '--format')) {
            return new CommandResult(1, errorOutput: 'not found');
        }

        if (str_contains($joined, '{{.State.Running}}')) {
            return new CommandResult(0, 'true healthy');
        }

        if (str_contains($joined, ' container run ')) {
            return new CommandResult(0, str_repeat('c', 64));
        }

        return new CommandResult(0, 'ok');
    }
}
