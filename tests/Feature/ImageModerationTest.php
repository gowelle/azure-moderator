<?php

namespace Tests\Feature;

use Gowelle\AzureModerator\Facades\AzureModerator;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageModerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'azure-moderator.endpoint' => 'https://test.cognitiveservices.azure.com',
            'azure-moderator.api_key' => 'test-key',
            'azure-moderator.high_severity_threshold' => 3,
        ]);
    }

    /** @test */
    public function it_can_moderate_image_by_url_and_approve_safe_content(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Hate', 'severity' => 0],
                    ['category' => 'SelfHarm', 'severity' => 0],
                    ['category' => 'Sexual', 'severity' => 0],
                    ['category' => 'Violence', 'severity' => 0],
                ],
            ], 200),
        ]);

        $result = AzureModerator::moderateImage('https://example.com/safe-image.jpg');

        expect($result)
            ->toBeArray()
            ->toHaveKey('status', 'approved')
            ->toHaveKey('reason', null)
            ->toHaveKey('scores');

        expect($result['scores'])->toHaveCount(4);
    }

    /** @test */
    public function it_can_moderate_image_by_url_and_flag_unsafe_content(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Hate', 'severity' => 0],
                    ['category' => 'SelfHarm', 'severity' => 0],
                    ['category' => 'Sexual', 'severity' => 6],
                    ['category' => 'Violence', 'severity' => 2],
                ],
            ], 200),
        ]);

        $result = AzureModerator::moderateImage('https://example.com/unsafe-image.jpg');

        expect($result)
            ->toBeArray()
            ->toHaveKey('status', 'flagged')
            ->toHaveKey('reason', 'Sexual')
            ->toHaveKey('scores');
    }

    /** @test */
    public function it_can_moderate_image_with_base64_encoding(): void
    {
        $base64Image = base64_encode('fake-image-data');

        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Hate', 'severity' => 0],
                    ['category' => 'SelfHarm', 'severity' => 0],
                    ['category' => 'Sexual', 'severity' => 0],
                    ['category' => 'Violence', 'severity' => 0],
                ],
            ], 200),
        ]);

        $result = AzureModerator::moderateImage($base64Image, encoding: 'base64');

        expect($result)
            ->toBeArray()
            ->toHaveKey('status', 'approved');

        // Verify the request payload
        Http::assertSent(function ($request) use ($base64Image) {
            $data = $request->data();
            return isset($data['image']['content']) &&
                   $data['image']['content'] === $base64Image;
        });
    }

    /** @test */
    public function it_sends_correct_payload_for_url_based_images(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Hate', 'severity' => 0],
                    ['category' => 'SelfHarm', 'severity' => 0],
                    ['category' => 'Sexual', 'severity' => 0],
                    ['category' => 'Violence', 'severity' => 0],
                ],
            ], 200),
        ]);

        $imageUrl = 'https://example.com/test-image.jpg';
        AzureModerator::moderateImage($imageUrl);

        Http::assertSent(function ($request) use ($imageUrl) {
            $data = $request->data();
            return isset($data['image']['url']) &&
                   $data['image']['url'] === $imageUrl;
        });
    }

    /** @test */
    public function it_can_moderate_image_with_specific_categories(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Sexual', 'severity' => 0],
                    ['category' => 'Violence', 'severity' => 0],
                ],
            ], 200),
        ]);

        $result = AzureModerator::moderateImage(
            'https://example.com/image.jpg',
            categories: ['Sexual', 'Violence']
        );

        expect($result)->toHaveKey('status', 'approved');

        Http::assertSent(function ($request) {
            $data = $request->data();
            return $data['categories'] === ['Sexual', 'Violence'];
        });
    }

    /** @test */
    public function it_flags_content_when_multiple_categories_exceed_threshold(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Hate', 'severity' => 5],
                    ['category' => 'SelfHarm', 'severity' => 0],
                    ['category' => 'Sexual', 'severity' => 6],
                    ['category' => 'Violence', 'severity' => 7],
                ],
            ], 200),
        ]);

        $result = AzureModerator::moderateImage('https://example.com/image.jpg');

        expect($result)
            ->toHaveKey('status', 'flagged')
            ->toHaveKey('reason', 'Hate, Sexual, Violence');
    }

    /** @test */
    public function it_throws_exception_for_empty_image(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image cannot be empty');

        AzureModerator::moderateImage('');
    }

    /** @test */
    public function it_throws_exception_for_invalid_encoding(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encoding must be either "url" or "base64"');

        AzureModerator::moderateImage('https://example.com/image.jpg', encoding: 'invalid');
    }

    /** @test */
    public function it_throws_exception_for_invalid_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid image URL provided');

        AzureModerator::moderateImage('not-a-valid-url', encoding: 'url');
    }

    /** @test */
    public function it_throws_exception_for_oversized_base64_image(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Base64 image data exceeds maximum size of 4MB (approximately 3MB original image size)');

        // Create a string larger than 4MB
        $largeBase64 = str_repeat('a', 4194305);

        AzureModerator::moderateImage($largeBase64, encoding: 'base64');
    }

    /** @test */
    public function it_returns_approved_on_api_error_with_graceful_degradation(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'error' => [
                    'code' => 'InvalidRequest',
                    'message' => 'The request is invalid',
                ],
            ], 400),
        ]);

        $result = AzureModerator::moderateImage('https://example.com/image.jpg');

        expect($result)
            ->toBeArray()
            ->toHaveKey('status', 'approved')
            ->toHaveKey('reason', null)
            ->toHaveKey('scores', null);
    }

    /** @test */
    public function it_retries_on_transient_failures(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::sequence()
                ->push(['error' => 'Server error'], 503)
                ->push(['error' => 'Server error'], 503)
                ->push([
                    'categoriesAnalysis' => [
                        ['category' => 'Hate', 'severity' => 0],
                        ['category' => 'SelfHarm', 'severity' => 0],
                        ['category' => 'Sexual', 'severity' => 0],
                        ['category' => 'Violence', 'severity' => 0],
                    ],
                ], 200),
        ]);

        $result = AzureModerator::moderateImage('https://example.com/image.jpg');

        expect($result)->toHaveKey('status', 'approved');

        // Should have made 3 attempts
        Http::assertSentCount(3);
    }

    /** @test */
    public function it_respects_custom_severity_threshold(): void
    {
        config(['azure-moderator.high_severity_threshold' => 6]);

        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Hate', 'severity' => 0],
                    ['category' => 'SelfHarm', 'severity' => 0],
                    ['category' => 'Sexual', 'severity' => 5],
                    ['category' => 'Violence', 'severity' => 0],
                ],
            ], 200),
        ]);

        $result = AzureModerator::moderateImage('https://example.com/image.jpg');

        // Should be approved because severity 5 is below threshold of 6
        expect($result)->toHaveKey('status', 'approved');
    }

    /** @test */
    public function it_includes_all_scores_in_response(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Hate', 'severity' => 1],
                    ['category' => 'SelfHarm', 'severity' => 2],
                    ['category' => 'Sexual', 'severity' => 0],
                    ['category' => 'Violence', 'severity' => 1],
                ],
            ], 200),
        ]);

        $result = AzureModerator::moderateImage('https://example.com/image.jpg');

        expect($result['scores'])
            ->toHaveCount(4)
            ->each(function ($score) {
                $score->toHaveKeys(['category', 'severity']);
            });
    }
}
