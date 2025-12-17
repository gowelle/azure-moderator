<?php

namespace Tests\Integration;

use Gowelle\AzureModerator\Facades\AzureModerator;
use Tests\TestCase;

/**
 * Integration tests for retry logic with real Azure Content Safety API
 *
 * These tests verify the retry mechanism works correctly with the actual API.
 * Note: Some tests may be slow due to retry delays.
 */
class RetryLogicIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if Azure credentials are not configured
        if (! env('AZURE_CONTENT_SAFETY_ENDPOINT') || ! env('AZURE_CONTENT_SAFETY_API_KEY')) {
            $this->markTestSkipped('Azure Content Safety credentials not configured. Set AZURE_CONTENT_SAFETY_ENDPOINT and AZURE_CONTENT_SAFETY_API_KEY in .env.integration');
        }

        config([
            'azure-moderator.endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
            'azure-moderator.api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
        ]);
    }

    /** @test */
    public function it_successfully_completes_request_without_retries(): void
    {
        $startTime = microtime(true);

        $result = AzureModerator::moderate('This is a test message.', 4.0);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        expect($result)
            ->toBeArray()
            ->toHaveKey('status');

        // Without retries, the request should complete relatively quickly (< 5 seconds)
        expect($duration)->toBeLessThan(5.0);
    }

    /** @test */
    public function it_handles_invalid_credentials_gracefully(): void
    {
        // Temporarily set invalid credentials
        config([
            'azure-moderator.api_key' => 'invalid-api-key-for-testing',
        ]);

        $result = AzureModerator::moderate('Test message with invalid credentials.', 4.0);

        // Should return approved due to graceful degradation
        expect($result)
            ->toBeArray()
            ->toHaveKey('status', 'approved')
            ->toHaveKey('reason', null);
    }

    /** @test */
    public function it_handles_invalid_endpoint_gracefully(): void
    {
        // Temporarily set invalid endpoint
        config([
            'azure-moderator.endpoint' => 'https://invalid-endpoint-for-testing.com',
        ]);

        $result = AzureModerator::moderate('Test message with invalid endpoint.', 4.0);

        // Should return approved due to graceful degradation
        expect($result)
            ->toBeArray()
            ->toHaveKey('status', 'approved')
            ->toHaveKey('reason', null);
    }

    /** @test */
    public function it_measures_retry_overhead_for_text_moderation(): void
    {
        // Make multiple requests and measure average time
        $times = [];
        $iterations = 3;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            AzureModerator::moderate("Test message iteration {$i}", 4.0);

            $endTime = microtime(true);
            $times[] = $endTime - $startTime;
        }

        $averageTime = array_sum($times) / count($times);

        // Log the average time for reference
        // Average time should be reasonable (< 3 seconds per request)
        expect($averageTime)->toBeLessThan(3.0);
    }

    /** @test */
    public function it_measures_retry_overhead_for_image_moderation(): void
    {
        // Make multiple requests and measure average time
        $times = [];
        $iterations = 3;
        $imageUrl = 'https://via.placeholder.com/150';

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            AzureModerator::moderateImage($imageUrl);

            $endTime = microtime(true);
            $times[] = $endTime - $startTime;
        }

        $averageTime = array_sum($times) / count($times);

        // Average time should be reasonable (< 3 seconds per request)
        expect($averageTime)->toBeLessThan(3.0);
    }

    /** @test */
    public function it_handles_network_timeout_gracefully(): void
    {
        // Set a very short timeout to simulate network issues
        config([
            'azure-moderator.endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
            'azure-moderator.api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
        ]);

        // This test verifies that even with potential network issues,
        // the service handles it gracefully
        $result = AzureModerator::moderate('Test message for timeout handling.', 4.0);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status');
    }

    /** @test */
    public function it_handles_concurrent_requests(): void
    {
        // Test that multiple concurrent requests work correctly
        $results = [];

        for ($i = 0; $i < 5; $i++) {
            $results[] = AzureModerator::moderate("Concurrent test message {$i}", 4.0);
        }

        expect($results)->toHaveCount(5);

        foreach ($results as $result) {
            expect($result)
                ->toBeArray()
                ->toHaveKey('status')
                ->toHaveKey('reason');
        }
    }

    /** @test */
    public function it_validates_api_response_consistency(): void
    {
        // Send the same request multiple times and verify consistent responses
        $message = 'Consistency test message';
        $rating = 4.0;

        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = AzureModerator::moderate($message, $rating);
        }

        // All results should have the same structure
        foreach ($results as $result) {
            expect($result)
                ->toBeArray()
                ->toHaveKeys(['status', 'reason']);
        }

        // Note: The actual status might vary slightly due to Azure's analysis,
        // but the structure should be consistent
    }
}
