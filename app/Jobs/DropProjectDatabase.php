<?php

namespace App\Jobs;

use App\Contracts\DatabaseServer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DropProjectDatabase implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public string $databaseName, public string $username) {}

    public function handle(DatabaseServer $server): void
    {
        $server->drop($this->databaseName, $this->username);
    }
}
