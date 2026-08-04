<?php

namespace App\Contracts;

interface DatabaseServer
{
    public function provision(string $database, string $username, string $password): void;

    public function drop(string $database, string $username): void;

    public function rotatePassword(string $username, string $password): void;

    public function sizeBytes(string $database): int;

    public function setReadOnly(string $database, string $username, bool $readOnly): void;
}
