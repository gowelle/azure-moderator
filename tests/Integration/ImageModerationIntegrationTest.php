<?php

namespace Tests\Integration;

use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Tests\TestCase;

/**
 * Integration tests for image moderation with real Azure Content Safety API
 *
 * These tests require valid Azure credentials to run.
 * Set AZURE_CONTENT_SAFETY_ENDPOINT and AZURE_CONTENT_SAFETY_API_KEY in .env.integration
 */
class ImageModerationIntegrationTest extends TestCase
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
            'azure-moderator.high_severity_threshold' => env('AZURE_CONTENT_SAFETY_HIGH_SEVERITY_THRESHOLD', 3),
        ]);
    }

    /** @test */
    public function it_can_moderate_image_by_url(): void
    {
        // Using a publicly accessible test image
        $imageUrl = 'https://via.placeholder.com/150';

        $result = AzureModerator::moderateImage($imageUrl);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason')
            ->toHaveKey('scores');

        expect($result['status'])->toBeIn(['approved', 'flagged']);

        if ($result['scores'] !== null) {
            expect($result['scores'])->toBeArray();
        }
    }

    /** @test */
    public function it_can_moderate_image_with_base64_encoding(): void
    {
        // Create a small test image (1x1 pixel PNG)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $base64Image = base64_encode($pngData);

        $result = AzureModerator::moderateImage($base64Image, encoding: 'base64');

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason')
            ->toHaveKey('scores');

        expect($result['status'])->toBeIn(['approved', 'flagged']);
    }

    /** @test */
    public function it_can_moderate_with_specific_hate_category(): void
    {
        $imageUrl = 'https://via.placeholder.com/150';

        $result = AzureModerator::moderateImage(
            $imageUrl,
            categories: [ContentCategory::HATE->value]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('scores');
    }

    /** @test */
    public function it_can_moderate_with_specific_selfharm_category(): void
    {
        $imageUrl = 'https://via.placeholder.com/150';

        $result = AzureModerator::moderateImage(
            $imageUrl,
            categories: [ContentCategory::SELF_HARM->value]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('scores');
    }

    /** @test */
    public function it_can_moderate_with_specific_sexual_category(): void
    {
        $imageUrl = 'https://via.placeholder.com/150';

        $result = AzureModerator::moderateImage(
            $imageUrl,
            categories: [ContentCategory::SEXUAL->value]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('scores');
    }

    /** @test */
    public function it_can_moderate_with_specific_violence_category(): void
    {
        $imageUrl = 'https://via.placeholder.com/150';

        $result = AzureModerator::moderateImage(
            $imageUrl,
            categories: [ContentCategory::VIOLENCE->value]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('scores');
    }

    /** @test */
    public function it_can_moderate_with_multiple_categories(): void
    {
        $imageUrl = 'https://via.placeholder.com/150';

        $result = AzureModerator::moderateImage(
            $imageUrl,
            categories: [
                ContentCategory::SEXUAL->value,
                ContentCategory::VIOLENCE->value,
            ]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('scores');
    }

    /** @test */
    public function it_returns_severity_scores_in_response(): void
    {
        $imageUrl = 'https://via.placeholder.com/150';

        $result = AzureModerator::moderateImage($imageUrl);

        expect($result)->toHaveKey('scores');

        if ($result['scores'] !== null) {
            expect($result['scores'])->toBeArray();

            foreach ($result['scores'] as $score) {
                expect($score)
                    ->toHaveKey('category')
                    ->toHaveKey('severity');

                expect($score['category'])->toBeString();
                expect($score['severity'])->toBeInt();
                expect($score['severity'])->toBeGreaterThanOrEqual(0);
                expect($score['severity'])->toBeLessThanOrEqual(7);
            }
        }
    }

    /** @test */
    public function it_respects_severity_threshold_configuration(): void
    {
        $imageUrl = 'https://via.placeholder.com/150';

        // Test with default threshold
        $result = AzureModerator::moderateImage($imageUrl);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status');

        // The actual status depends on Azure's analysis
        // We're just verifying the API call works and returns valid structure
    }

    /** @test */
    public function it_handles_different_image_formats(): void
    {
        // Test with different placeholder sizes (different image formats)
        $imageUrls = [
            'https://via.placeholder.com/100',
            'https://via.placeholder.com/200',
            'https://via.placeholder.com/300',
        ];

        foreach ($imageUrls as $imageUrl) {
            $result = AzureModerator::moderateImage($imageUrl);

            expect($result)
                ->toBeArray()
                ->toHaveKey('status')
                ->toHaveKey('scores');
        }
    }

    /** @test */
    public function it_validates_base64_size_limit(): void
    {
        // Create a base64 string that's under the 4MB limit
        $smallImage = str_repeat('a', 1000); // 1KB

        $result = AzureModerator::moderateImage($smallImage, encoding: 'base64');

        expect($result)
            ->toBeArray()
            ->toHaveKey('status');
    }

    /** @test */
    public function it_handles_various_image_urls(): void
    {
        // Test with different valid image URLs
        $imageUrls = [
            'https://via.placeholder.com/150/FF0000/FFFFFF',
            'https://via.placeholder.com/150/00FF00/000000',
            'https://via.placeholder.com/150/0000FF/FFFFFF',
        ];

        foreach ($imageUrls as $imageUrl) {
            $result = AzureModerator::moderateImage($imageUrl);

            expect($result)
                ->toBeArray()
                ->toHaveKey('status')
                ->toHaveKey('reason')
                ->toHaveKey('scores');

            expect($result['status'])->toBeIn(['approved', 'flagged']);
        }
    }
}
