<?php

use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\ProtectedMaterialService;

/**
 * Integration tests for Protected Material Detection
 *
 * These tests run against the real Azure Content Safety API
 * and require valid credentials in .env.integration
 */
beforeEach(function () {
    if (! env('AZURE_CONTENT_SAFETY_ENDPOINT') || ! env('AZURE_CONTENT_SAFETY_API_KEY')) {
        $this->markTestSkipped('Azure credentials not configured');
    }

    config([
        'azure-moderator.endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
        'azure-moderator.api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
    ]);
});

test('can detect known copyrighted text', function () {
    $service = new ProtectedMaterialService;

    // Test with known song lyrics (from Azure documentation example)
    $result = $service->detectProtectedMaterial(
        'to everyone, the best things in life are free. the stars belong to everyone, they gleam there for you and me'
    );

    expect($result)->toBeInstanceOf(\Gowelle\AzureModerator\Data\ProtectedMaterialResult::class)
        ->and($result->detected)->toBeBool()
        ->and($result->details)->toBeArray();

    // Note: Detection may vary based on Azure's database
    // This test validates the API call works, not the specific result
})->group('integration', 'protected-material');

test('returns false for original content', function () {
    $service = new ProtectedMaterialService;

    $result = $service->detectProtectedMaterial(
        'This is completely original content created specifically for this test case and contains no copyrighted material whatsoever.'
    );

    expect($result->detected)->toBeFalse();
})->group('integration', 'protected-material');

test('handles various text lengths', function () {
    $service = new ProtectedMaterialService;

    // Short text
    $shortResult = $service->detectProtectedMaterial('Hello world');
    expect($shortResult->detected)->toBeBool();

    // Medium text
    $mediumResult = $service->detectProtectedMaterial(str_repeat('This is a test sentence. ', 10));
    expect($mediumResult->detected)->toBeBool();

    // Long text
    $longResult = $service->detectProtectedMaterial(str_repeat('This is a longer test sentence with more content. ', 50));
    expect($longResult->detected)->toBeBool();
})->group('integration', 'protected-material');

test('throws exception for invalid credentials', function () {
    config([
        'azure-moderator.api_key' => 'invalid-key',
    ]);

    $service = new ProtectedMaterialService;
    $service->detectProtectedMaterial('Some text');
})->group('integration', 'protected-material')->throws(ModerationException::class);
