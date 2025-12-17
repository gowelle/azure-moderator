<?php

namespace Tests\Integration;

use Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Tests\TestCase;

/**
 * Integration tests for AzureModerator facade with real Azure API
 */
class FacadeIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if Azure credentials are not configured
        if (! env('AZURE_CONTENT_SAFETY_ENDPOINT') || ! env('AZURE_CONTENT_SAFETY_API_KEY')) {
            $this->markTestSkipped('Azure Content Safety credentials not configured');
        }

        config([
            'azure-moderator.endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
            'azure-moderator.api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
        ]);
    }

    /** @test */
    public function facade_resolves_to_service_contract(): void
    {
        $service = app(AzureContentSafetyServiceContract::class);

        expect($service)->toBeInstanceOf(AzureContentSafetyServiceContract::class);
    }

    /** @test */
    public function facade_can_moderate_text(): void
    {
        $result = AzureModerator::moderate('Test message via facade', 4.0);

        expect($result)
            ->toBeInstanceOf(\Gowelle\AzureModerator\Data\ModerationResult::class)
            ->and($result->status)->toBeInstanceOf(\Gowelle\AzureModerator\Enums\ModerationStatus::class);
    }

    /** @test */
    public function facade_can_moderate_image(): void
    {
        $result = AzureModerator::moderateImage('https://via.placeholder.com/150');

        expect($result)
            ->toBeInstanceOf(\Gowelle\AzureModerator\Data\ModerationResult::class)
            ->and($result->status)->toBeInstanceOf(\Gowelle\AzureModerator\Enums\ModerationStatus::class)
            ->and($result->categoriesAnalysis)->toBeArray();
    }

    /** @test */
    public function facade_uses_configured_values(): void
    {
        // Verify configuration is loaded correctly
        expect(config('azure-moderator.endpoint'))->toBe(env('AZURE_CONTENT_SAFETY_ENDPOINT'));
        expect(config('azure-moderator.api_key'))->toBe(env('AZURE_CONTENT_SAFETY_API_KEY'));
    }

    /** @test */
    public function service_provider_registers_service(): void
    {
        // Verify service provider registered the service
        expect(app()->bound(AzureContentSafetyServiceContract::class))->toBeTrue();
    }

    /** @test */
    public function facade_accessor_returns_correct_value(): void
    {
        $reflection = new \ReflectionClass(AzureModerator::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke(null);

        expect($accessor)->toBe(AzureContentSafetyServiceContract::class);
    }
}
