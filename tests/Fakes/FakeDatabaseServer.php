<?php

namespace Tests\Fakes;

use App\Contracts\DatabaseServer;
use RuntimeException;

class FakeDatabaseServer implements DatabaseServer
{
    /** @var list<array{database: string, username: string, password: string}> */
    public array $provisioned = [];

    /** @var list<array{database: string, username: string}> */
    public array $dropped = [];

    /** @var list<array{username: string, password: string}> */
    public array $rotated = [];

    /** @var array<string, int> */
    public array $sizes = [];

    /** @var list<array{database: string, username: string, read_only: bool}> */
    public array $accessChanges = [];

    public ?string $failure = null;

    public function provision(string $database, string $username, string $password): void
    {
        $this->failWhenConfigured();
        $this->provisioned[] = compact('database', 'username', 'password');
    }

    public function drop(string $database, string $username): void
    {
        $this->failWhenConfigured();
        $this->dropped[] = compact('database', 'username');
    }

    public function rotatePassword(string $username, string $password): void
    {
        $this->failWhenConfigured();
        $this->rotated[] = compact('username', 'password');
    }

    public function sizeBytes(string $database): int
    {
        $this->failWhenConfigured();

        return $this->sizes[$database] ?? 0;
    }

    public function setReadOnly(string $database, string $username, bool $readOnly): void
    {
        $this->failWhenConfigured();
        $this->accessChanges[] = ['database' => $database, 'username' => $username, 'read_only' => $readOnly];
    }

    private function failWhenConfigured(): void
    {
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }
    }
}
