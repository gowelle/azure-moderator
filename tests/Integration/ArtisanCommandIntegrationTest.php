<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Integration tests for Artisan commands with real Azure API
 */
class ArtisanCommandIntegrationTest extends TestCase
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
    public function test_image_command_exists(): void
    {
        $commands = Artisan::all();

        expect($commands)->toHaveKey('azure-moderator:test-image');
    }

    /** @test */
    public function test_image_command_runs_successfully(): void
    {
        $exitCode = Artisan::call('azure-moderator:test-image', [
            'image' => 'https://via.placeholder.com/150',
        ]);

        expect($exitCode)->toBe(0);
    }

    /** @test */
    public function test_image_command_with_categories_option(): void
    {
        $exitCode = Artisan::call('azure-moderator:test-image', [
            'image' => 'https://via.placeholder.com/150',
            '--categories' => 'Sexual,Violence',
        ]);

        expect($exitCode)->toBe(0);
    }

    /** @test */
    public function test_image_command_output_contains_result(): void
    {
        Artisan::call('azure-moderator:test-image', [
            'image' => 'https://via.placeholder.com/150',
        ]);

        $output = Artisan::output();

        expect($output)->toContain('Image Moderation Result');
    }

    /** @test */
    public function test_image_command_handles_invalid_url(): void
    {
        $exitCode = Artisan::call('azure-moderator:test-image', [
            'image' => 'not-a-valid-url',
        ]);

        // Should handle error gracefully
        expect($exitCode)->toBeGreaterThan(0);
    }
}
