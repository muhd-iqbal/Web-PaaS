<?php

namespace App\Providers;

use App\Contracts\BillingGateway;
use App\Contracts\CommandRunner;
use App\Contracts\ContainerRuntime;
use App\Contracts\DatabaseServer;
use App\Models\Project;
use App\Observers\ProjectObserver;
use App\Services\DockerContainerRuntime;
use App\Services\MySqlDatabaseServer;
use App\Services\SymfonyCommandRunner;
use App\Services\ToyyibPayBillingGateway;
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
        $this->app->bind(DatabaseServer::class, MySqlDatabaseServer::class);
        $this->app->bind(BillingGateway::class, ToyyibPayBillingGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Project::observe(ProjectObserver::class);
    }
}
