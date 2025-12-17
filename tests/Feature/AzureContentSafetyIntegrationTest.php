<?php

namespace Tests\Feature;

use Gowelle\AzureModerator\AzureContentSafetyServiceProvider;
use Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract;
use Orchestra\Testbench\TestCase;

class AzureContentSafetyIntegrationTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            AzureContentSafetyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('azure-moderator', [
            'endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
            'api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
        ]);
    }

    /** @test */
    public function it_can_connect_to_azure_api()
    {
        // Skip test if no credentials
        if (! env('AZURE_CONTENT_SAFETY_ENDPOINT') || ! env('AZURE_CONTENT_SAFETY_API_KEY')) {
            $this->markTestSkipped('Azure credentials not available.');
        }

        $service = app(AzureContentSafetyServiceContract::class);

        $response = $service->moderate('This is a test message', 5.0);

        $this->assertInstanceOf(\Gowelle\AzureModerator\Data\ModerationResult::class, $response);
        $this->assertTrue(in_array($response->status->value, ['approved', 'flagged']));
    }
}
