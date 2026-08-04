<?php

namespace App\ValueObjects;

readonly class ContainerInstance
{
    public function __construct(
        public string $name,
        public string $id,
    ) {}
}
