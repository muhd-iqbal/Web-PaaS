<?php

namespace Tests\Unit;

use App\Services\SymfonyCommandRunner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SymfonyCommandRunnerTest extends TestCase
{
    public function test_it_uses_a_private_writable_docker_config_directory(): void
    {
        $directory = storage_path('framework/testing/docker-config-'.Str::uuid());
        config()->set('hosting.deployment.docker_config', $directory);

        try {
            $result = (new SymfonyCommandRunner)->run([
                PHP_BINARY,
                '-r',
                'echo getenv("DOCKER_CONFIG");',
            ], 5);

            $this->assertTrue($result->successful());
            $this->assertSame($directory, $result->output);
            $this->assertDirectoryExists($directory);
            $this->assertSame('0700', substr(sprintf('%o', fileperms($directory)), -4));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
