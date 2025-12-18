<?php

namespace Gowelle\AzureModerator;

use Gowelle\AzureModerator\Commands\BlocklistManagementCommand;
use Gowelle\AzureModerator\Commands\TestImageModerationCommand;
use Gowelle\AzureModerator\Commands\TestMultimodalCommand;
use Gowelle\AzureModerator\Commands\TestProtectedMaterialCommand;
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
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('azure-moderator')
            ->hasConfigFile('azure-moderator')
            ->hasCommands([
                TestImageModerationCommand::class,
                BlocklistManagementCommand::class,
                TestProtectedMaterialCommand::class,
                TestMultimodalCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
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

        $this->app->singleton(BlocklistService::class, function ($app) {
            return new BlocklistService;
        });

        $this->app->singleton(ProtectedMaterialService::class, function ($app) {
            return new ProtectedMaterialService;
        });

        $this->app->singleton(MultimodalService::class, function ($app) {
            return new MultimodalService;
        });
    }
}

