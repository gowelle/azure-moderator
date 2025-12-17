<?php

namespace Tests\Integration;

use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Tests\TestCase;

/**
 * Integration tests for text moderation with real Azure Content Safety API
 *
 * These tests require valid Azure credentials to run.
 * Set AZURE_CONTENT_SAFETY_ENDPOINT and AZURE_CONTENT_SAFETY_API_KEY in .env.integration
 */
class TextModerationIntegrationTest extends TestCase
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
            'azure-moderator.low_rating_threshold' => env('AZURE_CONTENT_SAFETY_LOW_RATING_THRESHOLD', 2),
            'azure-moderator.high_severity_threshold' => env('AZURE_CONTENT_SAFETY_HIGH_SEVERITY_THRESHOLD', 3),
        ]);
    }

    /** @test */
    public function it_can_moderate_safe_text_content(): void
    {
        $result = AzureModerator::moderate('This is a wonderful product. I really enjoyed using it!', 5.0);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');

        expect($result['status'])->toBeIn(['approved', 'flagged']);
    }

    /** @test */
    public function it_flags_content_with_low_rating(): void
    {
        $result = AzureModerator::moderate('This product is okay.', 1.5);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status', 'flagged')
            ->toHaveKey('reason', 'low_rating');
    }

    /** @test */
    public function it_can_moderate_with_specific_hate_category(): void
    {
        $result = AzureModerator::moderate(
            'This is a test message for hate category.',
            4.0,
            [ContentCategory::HATE->value]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');
    }

    /** @test */
    public function it_can_moderate_with_specific_selfharm_category(): void
    {
        $result = AzureModerator::moderate(
            'This is a test message for self-harm category.',
            4.0,
            [ContentCategory::SELF_HARM->value]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');
    }

    /** @test */
    public function it_can_moderate_with_specific_sexual_category(): void
    {
        $result = AzureModerator::moderate(
            'This is a test message for sexual category.',
            4.0,
            [ContentCategory::SEXUAL->value]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');
    }

    /** @test */
    public function it_can_moderate_with_specific_violence_category(): void
    {
        $result = AzureModerator::moderate(
            'This is a test message for violence category.',
            4.0,
            [ContentCategory::VIOLENCE->value]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');
    }

    /** @test */
    public function it_can_moderate_with_multiple_categories(): void
    {
        $result = AzureModerator::moderate(
            'This is a test message for multiple categories.',
            4.0,
            [
                ContentCategory::HATE->value,
                ContentCategory::SEXUAL->value,
                ContentCategory::VIOLENCE->value,
            ]
        );

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');
    }

    /** @test */
    public function it_respects_severity_threshold_configuration(): void
    {
        // Test with default threshold
        $result = AzureModerator::moderate('This is a neutral test message.', 4.0);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status');

        // The actual status depends on Azure's analysis
        // We're just verifying the API call works and returns valid structure
    }

    /** @test */
    public function it_handles_various_rating_values(): void
    {
        $ratings = [0.5, 1.0, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0];

        foreach ($ratings as $rating) {
            $result = AzureModerator::moderate("Test content with rating {$rating}", $rating);

            expect($result)
                ->toBeArray()
                ->toHaveKey('status')
                ->toHaveKey('reason');

            expect($result['status'])->toBeIn(['approved', 'flagged']);
        }
    }

    /** @test */
    public function it_returns_valid_response_structure(): void
    {
        $result = AzureModerator::moderate('Test message for structure validation.', 4.0);

        expect($result)
            ->toBeArray()
            ->toHaveKeys(['status', 'reason']);

        expect($result['status'])->toBeString();

        if ($result['reason'] !== null) {
            expect($result['reason'])->toBeString();
        }
    }

    /** @test */
    public function it_handles_empty_text_gracefully(): void
    {
        // Azure API should handle empty text
        // The service should either approve or flag based on API response
        $result = AzureModerator::moderate('', 4.0);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status');
    }

    /** @test */
    public function it_handles_long_text_content(): void
    {
        // Test with longer content (but within Azure's limits)
        $longText = str_repeat('This is a test sentence. ', 50);

        $result = AzureModerator::moderate($longText, 4.0);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');
    }

    /** @test */
    public function it_handles_special_characters_in_text(): void
    {
        $textWithSpecialChars = "Test with special chars: @#$%^&*()_+-=[]{}|;':\",./<>?";

        $result = AzureModerator::moderate($textWithSpecialChars, 4.0);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');
    }

    /** @test */
    public function it_handles_unicode_characters(): void
    {
        $unicodeText = 'Test with unicode: 你好世界 🌍 مرحبا العالم';

        $result = AzureModerator::moderate($unicodeText, 4.0);

        expect($result)
            ->toBeArray()
            ->toHaveKey('status')
            ->toHaveKey('reason');
    }
}
