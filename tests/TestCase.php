<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            \Gowelle\AzureModerator\AzureContentSafetyServiceProvider::class,
        ];
    }

    private function createTestDatabase()
    {
        $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('test_reviews');

        $this->app['db']->connection()->getSchemaBuilder()->create('test_reviews', function ($table) {
            $table->id();
            $table->string('content');
            $table->float('rating');
            $table->string('status')->nullable();
            $table->string('moderation_reason')->nullable();
            $table->timestamps();
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
