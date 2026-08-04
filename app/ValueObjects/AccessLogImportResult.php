<?php

namespace App\ValueObjects;

final readonly class AccessLogImportResult
{
    /** @param list<int> $projectIds */
    public function __construct(
        public int $linesRead,
        public int $requestsImported,
        public array $projectIds,
        public bool $fileFound = true,
    ) {}
}
