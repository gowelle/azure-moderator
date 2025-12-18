<?php

namespace Tests\Integration;

use Gowelle\AzureModerator\Data\MultimodalResult;
use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Enums\ModerationStatus;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\MultimodalService;
use Tests\TestCase;

/**
 * Integration tests for multimodal analysis with real Azure Content Safety API (Preview)
 *
 * These tests require valid Azure credentials to run.
 * Set AZURE_CONTENT_SAFETY_ENDPOINT and AZURE_CONTENT_SAFETY_API_KEY in .env.integration
 *
 * NOTE: The Multimodal API is only available in certain Azure regions.
 * Tests will be skipped if the feature is not available in your region.
 *
 * @group multimodal
 */
class MultimodalIntegrationTest extends TestCase
{
    protected MultimodalService $service;

    protected static bool $regionSupported = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if Azure credentials are not configured
        if (! env('AZURE_CONTENT_SAFETY_ENDPOINT') || ! env('AZURE_CONTENT_SAFETY_API_KEY')) {
            $this->markTestSkipped('Azure Content Safety credentials not configured. Set AZURE_CONTENT_SAFETY_ENDPOINT and AZURE_CONTENT_SAFETY_API_KEY in .env.integration');
        }

        // Skip if we already know region doesn't support this feature
        if (! self::$regionSupported) {
            $this->markTestSkipped('Multimodal API is not available in your Azure region.');
        }

        config([
            'azure-moderator.endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
            'azure-moderator.api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
            'azure-moderator.high_severity_threshold' => env('AZURE_CONTENT_SAFETY_HIGH_SEVERITY_THRESHOLD', 3),
        ]);

        $this->service = new MultimodalService;
    }

    /**
     * Helper to check if multimodal API is available in region
     */
    protected function skipIfNotAvailableInRegion(ModerationException $e): void
    {
        $message = $e->getMessage();
        if (str_contains($message, 'not yet available') ||
            str_contains($message, 'Not Found') ||
            str_contains($message, 'not available')) {
            self::$regionSupported = false;
            $this->markTestSkipped('Multimodal API is not available in your Azure region. See: https://learn.microsoft.com/en-us/azure/ai-services/content-safety/overview#region-availability');
        }
    }

    /**
     * Helper to wrap API calls with region check
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function callWithRegionCheck(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ModerationException $e) {
            $this->skipIfNotAvailableInRegion($e);
            throw $e;
        }
    }

    /** @test */
    public function it_can_analyze_image_by_url(): void
    {
        $result = $this->callWithRegionCheck(fn () => $this->service->analyze(
            image: 'https://via.placeholder.com/150',
            encoding: 'url'
        ));

        expect($result)
            ->toBeInstanceOf(MultimodalResult::class)
            ->and($result->status)->toBeInstanceOf(ModerationStatus::class)
            ->and($result->categoriesAnalysis)->toBeArray();

        expect($result->status->value)->toBeIn(['approved', 'flagged']);
    }

    /** @test */
    public function it_can_analyze_image_with_base64_encoding(): void
    {
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $base64Image = base64_encode($pngData);

        $result = $this->callWithRegionCheck(fn () => $this->service->analyze(
            image: $base64Image,
            encoding: 'base64'
        ));

        expect($result)
            ->toBeInstanceOf(MultimodalResult::class)
            ->and($result->status)->toBeInstanceOf(ModerationStatus::class)
            ->and($result->categoriesAnalysis)->toBeArray();
    }

    /** @test */
    public function it_can_analyze_image_with_text(): void
    {
        $result = $this->callWithRegionCheck(fn () => $this->service->analyze(
            image: 'https://via.placeholder.com/150',
            text: 'This is a safe placeholder image',
            encoding: 'url'
        ));

        expect($result)
            ->toBeInstanceOf(MultimodalResult::class)
            ->and($result->status)->toBeInstanceOf(ModerationStatus::class);
    }

    /** @test */
    public function it_can_analyze_with_specific_categories(): void
    {
        $result = $this->callWithRegionCheck(fn () => $this->service->analyze(
            image: 'https://via.placeholder.com/150',
            encoding: 'url',
            categories: [
                ContentCategory::SEXUAL->value,
                ContentCategory::VIOLENCE->value,
            ]
        ));

        expect($result)
            ->toBeInstanceOf(MultimodalResult::class)
            ->and($result->categoriesAnalysis)->toBeArray();
    }

    /** @test */
    public function it_can_analyze_with_ocr_disabled(): void
    {
        $result = $this->callWithRegionCheck(fn () => $this->service->analyze(
            image: 'https://via.placeholder.com/150',
            encoding: 'url',
            enableOcr: false
        ));

        expect($result)
            ->toBeInstanceOf(MultimodalResult::class)
            ->and($result->status)->toBeInstanceOf(ModerationStatus::class);
    }

    /** @test */
    public function it_returns_severity_scores_in_response(): void
    {
        $result = $this->callWithRegionCheck(fn () => $this->service->analyze(
            image: 'https://via.placeholder.com/150',
            encoding: 'url'
        ));

        if (! empty($result->categoriesAnalysis)) {
            foreach ($result->categoriesAnalysis as $score) {
                expect($score)
                    ->toBeInstanceOf(\Gowelle\AzureModerator\Data\CategoryAnalysis::class);

                expect($score->severity)->toBeInt();
                expect($score->severity)->toBeGreaterThanOrEqual(0);
                expect($score->severity)->toBeLessThanOrEqual(7);
            }
        }
    }

    /** @test */
    public function it_can_convert_result_to_array(): void
    {
        $result = $this->callWithRegionCheck(fn () => $this->service->analyze(
            image: 'https://via.placeholder.com/150',
            encoding: 'url'
        ));

        $array = $result->toArray();

        expect($array)->toHaveKey('status')
            ->and($array)->toHaveKey('reason')
            ->and($array)->toHaveKey('categoriesAnalysis')
            ->and($array['status'])->toBeIn(['approved', 'flagged']);
    }

    /** @test */
    public function it_handles_combined_image_and_text_analysis(): void
    {
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $base64Image = base64_encode($pngData);

        $result = $this->callWithRegionCheck(fn () => $this->service->analyze(
            image: $base64Image,
            text: 'This is a caption describing the image content',
            encoding: 'base64',
            enableOcr: true
        ));

        expect($result)
            ->toBeInstanceOf(MultimodalResult::class)
            ->and($result->status)->toBeInstanceOf(ModerationStatus::class);
    }
}
