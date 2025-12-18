<?php

use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\MultimodalService;
use Gowelle\AzureModerator\Rules\SafeMultimodal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    config([
        'azure-moderator.endpoint' => 'https://test.cognitiveservices.azure.com',
        'azure-moderator.api_key' => 'test-api-key',
        'azure-moderator.high_severity_threshold' => 3,
        'azure-moderator.fail_on_api_error' => false,
    ]);

    // Bind the service
    app()->singleton(MultimodalService::class, fn () => new MultimodalService);
});

test('passes validation for safe multimodal content', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
                ['category' => 'Violence', 'severity' => 0],
            ],
        ], 200),
    ]);

    $file = UploadedFile::fake()->image('avatar.jpg');

    $validator = Validator::make(
        ['image' => $file],
        ['image' => ['required', new SafeMultimodal]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails validation for flagged multimodal content', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Violence', 'severity' => 6],
            ],
        ], 200),
    ]);

    $file = UploadedFile::fake()->image('violent.jpg');

    $validator = Validator::make(
        ['image' => $file],
        ['image' => ['required', new SafeMultimodal]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('image'))->toContain('Violence');
});

test('validates with associated text', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
            ],
        ], 200),
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    $validator = Validator::make(
        ['image' => $file],
        ['image' => ['required', new SafeMultimodal(text: 'This is my caption')]]
    );

    expect($validator->passes())->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->data()['text'] === 'This is my caption';
    });
});

test('validates with custom categories', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Sexual', 'severity' => 0],
            ],
        ], 200),
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    $validator = Validator::make(
        ['image' => $file],
        ['image' => ['required', new SafeMultimodal(categories: ['Sexual'])]]
    );

    expect($validator->passes())->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->data()['categories'] === ['Sexual'];
    });
});

test('fails for non-file input', function () {
    $validator = Validator::make(
        ['image' => 'not-a-file'],
        ['image' => ['required', new SafeMultimodal]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('image'))->toContain('uploaded file');
});

test('gracefully handles API errors when not strict', function () {
    config(['azure-moderator.fail_on_api_error' => false]);

    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'error' => ['message' => 'Service unavailable'],
        ], 503),
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    $validator = Validator::make(
        ['image' => $file],
        ['image' => ['required', new SafeMultimodal]]
    );

    // Should pass gracefully when not in strict mode
    expect($validator->passes())->toBeTrue();
});

test('fails validation on API error when strict mode enabled', function () {
    config(['azure-moderator.fail_on_api_error' => true]);

    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'error' => ['message' => 'Service unavailable'],
        ], 503),
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    $validator = Validator::make(
        ['image' => $file],
        ['image' => ['required', new SafeMultimodal]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('image'))->toContain('Unable to validate');
});

test('can disable OCR', function () {
    Http::fake([
        '*/contentsafety/imageWithText:analyze*' => Http::response([
            'categoriesAnalysis' => [],
        ], 200),
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    $validator = Validator::make(
        ['image' => $file],
        ['image' => ['required', new SafeMultimodal(enableOcr: false)]]
    );

    $validator->passes();

    Http::assertSent(function ($request) {
        return $request->data()['enableOcr'] === false;
    });
});
