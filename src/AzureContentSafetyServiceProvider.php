<?php

namespace Gowelle\AzureModerator;

use Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AzureContentSafetyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('azure-moderator')
            ->hasConfigFile('azure-moderator')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile('azure-moderator')
                    ->copyAndRegisterServiceProviderInApp();
            });
    }

    public function registeringPackage()
    {
        $this->app->when(AzureContentSafetyServiceContract::class)
            ->needs('$endpoint')
            ->give(config('azure-moderator.endpoint'));

        $this->app->when(AzureContentSafetyServiceContract::class)
            ->needs('$apiKey')
            ->give(config('azure-moderator.api_key'));

        $this->app->bind(AzureContentSafetyServiceContract::class, AzureContentSafetyService::class);

        $this->app->singleton('gowelle.azure-moderator', function ($app) {
            return app(AzureContentSafetyServiceContract::class);
        });
    }
}