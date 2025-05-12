<?php

namespace Gowelle\AzureModerator;

use Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for Azure Content Safety integration
 *
 * This provider registers the content moderation service and its configuration
 * with the Laravel service container.
 */
class AzureContentSafetyServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package settings and features
     *
     * @param Package $package
     * @return void
     */
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

    /**
     * Register package bindings with the service container
     *
     * @return void
     */
    public function registeringPackage()
    {
        $this->app->bind(AzureContentSafetyServiceContract::class, AzureContentSafetyService::class);

        $this->app->singleton('gowelle.azure-moderator', function ($app) {
            return new AzureContentSafetyService;
        });
    }
}