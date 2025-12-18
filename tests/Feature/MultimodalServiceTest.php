<?php

use Gowelle\AzureModerator\Data\MultimodalResult;
use Gowelle\AzureModerator\Enums\ModerationStatus;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\MultimodalService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'azure-moderator.endpoint' => 'https://test.cognitiveservices.azure.com',
        'azure-moderator.api_key' => 'test-api-key',
        'azure-moderator.high_severity_threshold' => 3,
    ]);
});

test('can analyze image without text', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
                ['category' => 'SelfHarm', 'severity' => 0],
                ['category' => 'Sexual', 'severity' => 0],
                ['category' => 'Violence', 'severity' => 0],
            ],
        ], 200),
    ]);

    $service = new MultimodalService;
    $result = $service->analyze(
        image: base64_encode('fake-image-data'),
        encoding: 'base64'
    );

    expect($result)->toBeInstanceOf(MultimodalResult::class)
        ->and($result->status)->toBe(ModerationStatus::APPROVED)
        ->and($result->isApproved())->toBeTrue()
        ->and($result->categoriesAnalysis)->toHaveCount(4);
});

test('can analyze image with text', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
                ['category' => 'Violence', 'severity' => 2],
            ],
        ], 200),
    ]);

    $service = new MultimodalService;
    $result = $service->analyze(
        image: base64_encode('fake-image-data'),
        text: 'This is a caption for the image',
        encoding: 'base64'
    );

    expect($result->isApproved())->toBeTrue();

    // Verify text was sent in request
    Http::assertSent(function ($request) {
        return $request->data()['text'] === 'This is a caption for the image';
    });
});

test('flags content with high severity scores', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
                ['category' => 'Violence', 'severity' => 5],
            ],
        ], 200),
    ]);

    $service = new MultimodalService;
    $result = $service->analyze(
        image: base64_encode('fake-image-data'),
    );

    expect($result->isFlagged())->toBeTrue()
        ->and($result->reason)->toContain('Violence');
});

test('supports URL encoding', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
            ],
        ], 200),
    ]);

    $service = new MultimodalService;
    $result = $service->analyze(
        image: 'https://example.com/image.jpg',
        encoding: 'url'
    );

    expect($result->isApproved())->toBeTrue();

    Http::assertSent(function ($request) {
        return isset($request->data()['image']['blobUrl']);
    });
});

test('throws exception for empty image', function () {
    $service = new MultimodalService;
    $service->analyze(image: '');
})->throws(\InvalidArgumentException::class, 'Image cannot be empty');

test('throws exception for invalid encoding', function () {
    $service = new MultimodalService;
    $service->analyze(image: 'data', encoding: 'invalid');
})->throws(\InvalidArgumentException::class, 'Encoding must be "base64" or "url"');

test('throws exception for invalid URL', function () {
    $service = new MultimodalService;
    $service->analyze(image: 'not-a-url', encoding: 'url');
})->throws(\InvalidArgumentException::class, 'Invalid image URL');

test('throws exception on API error', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'error' => [
                'code' => 'InvalidRequest',
                'message' => 'Invalid request',
            ],
        ], 400),
    ]);

    $service = new MultimodalService;
    $service->analyze(image: base64_encode('fake-image'));
})->throws(ModerationException::class);

test('retries on rate limit errors', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::sequence()
            ->push(['error' => ['code' => 'RateLimitExceeded']], 429)
            ->push(['error' => ['code' => 'RateLimitExceeded']], 429)
            ->push(['categoriesAnalysis' => [['category' => 'Hate', 'severity' => 0]]], 200),
    ]);

    $service = new MultimodalService;
    $result = $service->analyze(image: base64_encode('fake-image'));

    expect($result->isApproved())->toBeTrue();
    Http::assertSentCount(3);
});

test('sends OCR parameter correctly', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [],
        ], 200),
    ]);

    $service = new MultimodalService;
    $service->analyze(image: base64_encode('fake'), enableOcr: false);

    Http::assertSent(function ($request) {
        return $request->data()['enableOcr'] === false;
    });
});

test('filters to specified categories', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Sexual', 'severity' => 0],
                ['category' => 'Violence', 'severity' => 0],
            ],
        ], 200),
    ]);

    $service = new MultimodalService;
    $service->analyze(
        image: base64_encode('fake'),
        categories: ['Sexual', 'Violence']
    );

    Http::assertSent(function ($request) {
        return $request->data()['categories'] === ['Sexual', 'Violence'];
    });
});

test('result toArray returns expected format', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 2],
            ],
        ], 200),
    ]);

    $service = new MultimodalService;
    $result = $service->analyze(image: base64_encode('fake'));

    $array = $result->toArray();

    expect($array)->toHaveKey('status')
        ->and($array)->toHaveKey('reason')
        ->and($array)->toHaveKey('categoriesAnalysis')
        ->and($array['categoriesAnalysis'][0])->toHaveKey('category')
        ->and($array['categoriesAnalysis'][0])->toHaveKey('severity');
});
