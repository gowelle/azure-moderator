<?php

use Gowelle\AzureModerator\Data\ProtectedMaterialResult;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\ProtectedMaterialService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'azure-moderator.endpoint' => 'https://test.cognitiveservices.azure.com',
        'azure-moderator.api_key' => 'test-api-key',
    ]);
});

test('can detect protected material in text', function () {
    Http::fake([
        '*/contentsafety/text:detectProtectedMaterial*' => Http::response([
            'protectedMaterialAnalysis' => [
                'detected' => true,
            ],
        ], 200),
    ]);

    $service = new ProtectedMaterialService;
    $result = $service->detectProtectedMaterial('to everyone, the best things in life are free');

    expect($result)->toBeInstanceOf(ProtectedMaterialResult::class)
        ->and($result->detected)->toBeTrue()
        ->and($result->details)->toBeArray();
});

test('returns false when no protected material detected', function () {
    Http::fake([
        '*/contentsafety/text:detectProtectedMaterial*' => Http::response([
            'protectedMaterialAnalysis' => [
                'detected' => false,
            ],
        ], 200),
    ]);

    $service = new ProtectedMaterialService;
    $result = $service->detectProtectedMaterial('This is original content with no copyrighted material');

    expect($result)->toBeInstanceOf(ProtectedMaterialResult::class)
        ->and($result->detected)->toBeFalse();
});

test('throws exception for empty text', function () {
    $service = new ProtectedMaterialService;
    $service->detectProtectedMaterial('');
})->throws(\InvalidArgumentException::class, 'Text cannot be empty');

test('throws exception on API error', function () {
    Http::fake([
        '*/contentsafety/text:detectProtectedMaterial*' => Http::response([
            'error' => [
                'code' => 'InvalidRequest',
                'message' => 'Invalid request',
            ],
        ], 400),
    ]);

    $service = new ProtectedMaterialService;
    $service->detectProtectedMaterial('Some text');
})->throws(ModerationException::class);

test('retries on rate limit errors', function () {
    Http::fake([
        '*/contentsafety/text:detectProtectedMaterial*' => Http::sequence()
            ->push(['error' => ['code' => 'RateLimitExceeded']], 429)
            ->push(['error' => ['code' => 'RateLimitExceeded']], 429)
            ->push(['protectedMaterialAnalysis' => ['detected' => false]], 200),
    ]);

    $service = new ProtectedMaterialService;
    $result = $service->detectProtectedMaterial('Some text');

    expect($result->detected)->toBeFalse();
    Http::assertSentCount(3);
});

test('handles API response with analysis details', function () {
    Http::fake([
        '*/contentsafety/text:detectProtectedMaterial*' => Http::response([
            'protectedMaterialAnalysis' => [
                'detected' => true,
                'confidence' => 0.95,
                'source' => 'song_lyrics',
            ],
        ], 200),
    ]);

    $service = new ProtectedMaterialService;
    $result = $service->detectProtectedMaterial('Some copyrighted lyrics');

    expect($result->detected)->toBeTrue()
        ->and($result->details)->toHaveKey('confidence')
        ->and($result->details)->toHaveKey('source');
});
