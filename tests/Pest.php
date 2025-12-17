<?php

use Gowelle\AzureModerator\AzureContentSafetyServiceProvider;

uses(Orchestra\Testbench\TestCase::class)->in(__DIR__);

// Load integration test environment if available
if (file_exists(__DIR__.'/../.env.integration')) {
    $dotenv = \Dotenv\Dotenv::createMutable(__DIR__.'/../', '.env.integration');
    $dotenv->load();
}

function getPackageProviders($app)
{
    return [
        AzureContentSafetyServiceProvider::class,
    ];
}
