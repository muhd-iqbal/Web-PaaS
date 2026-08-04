<?php

namespace App\Providers;

use App\Contracts\CommandRunner;
use App\Contracts\ContainerRuntime;
use App\Models\Project;
use App\Observers\ProjectObserver;
use App\Services\DockerContainerRuntime;
use App\Services\SymfonyCommandRunner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CommandRunner::class, SymfonyCommandRunner::class);
        $this->app->bind(ContainerRuntime::class, DockerContainerRuntime::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Project::observe(ProjectObserver::class);
    }
}
