<?php

namespace Tests\Integration;

use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Rules\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * End-to-end integration tests for SafeImage validation rule with real Azure API
 */
class SafeImageValidationIntegrationTest extends TestCase
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
            'azure-moderator.high_severity_threshold' => 3,
            'azure-moderator.fail_on_api_error' => false,
        ]);
    }

    /** @test */
    public function it_validates_safe_uploaded_image(): void
    {
        // Create a small test image (1x1 pixel PNG)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('test.png', $pngData);

        $validator = Validator::make(
            ['image' => $file],
            ['image' => [new SafeImage]]
        );

        // The validation should pass (image is safe or API gracefully degrades)
        expect($validator->passes())->toBeTrue();
    }

    /** @test */
    public function it_validates_with_specific_categories(): void
    {
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('test.png', $pngData);

        $validator = Validator::make(
            ['image' => $file],
            ['image' => [new SafeImage([ContentCategory::SEXUAL->value, ContentCategory::VIOLENCE->value])]]
        );

        expect($validator->passes())->toBeTrue();
    }

    /** @test */
    public function it_fails_validation_for_non_uploaded_file(): void
    {
        $validator = Validator::make(
            ['image' => 'not-a-file'],
            ['image' => [new SafeImage]]
        );

        expect($validator->fails())->toBeTrue();
    }

    /** @test */
    public function it_handles_graceful_degradation_mode(): void
    {
        config(['azure-moderator.fail_on_api_error' => false]);

        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('test.png', $pngData);

        $validator = Validator::make(
            ['image' => $file],
            ['image' => [new SafeImage]]
        );

        // Should pass even if there are API issues
        expect($validator->passes())->toBeTrue();
    }

    /** @test */
    public function it_validates_different_image_formats(): void
    {
        // Test with different image formats
        $formats = [
            'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
            'jpg' => base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q=='),
        ];

        foreach ($formats as $ext => $data) {
            $file = UploadedFile::fake()->createWithContent("test.{$ext}", $data);

            $validator = Validator::make(
                ['image' => $file],
                ['image' => [new SafeImage]]
            );

            expect($validator->passes())->toBeTrue();
        }
    }
}
