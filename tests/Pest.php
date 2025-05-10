<?php

use Gowelle\AzureModerator\AzureContentSafetyServiceProvider;

uses(Orchestra\Testbench\TestCase::class)->in(__DIR__);

function getPackageProviders($app)
{
    return [
        AzureContentSafetyServiceProvider::class,
    ];
}