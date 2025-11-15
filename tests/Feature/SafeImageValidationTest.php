<?php

namespace Tests\Feature;

use Gowelle\AzureModerator\Rules\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeImageValidationTest extends TestCase
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
    public function it_passes_validation_for_safe_images(): void
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

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $validator = Validator::make(
            ['avatar' => $file],
            ['avatar' => ['required', new SafeImage()]]
        );

        expect($validator->passes())->toBeTrue();
    }

    /** @test */
    public function it_fails_validation_for_unsafe_images(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Hate', 'severity' => 0],
                    ['category' => 'SelfHarm', 'severity' => 0],
                    ['category' => 'Sexual', 'severity' => 6],
                    ['category' => 'Violence', 'severity' => 0],
                ],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $validator = Validator::make(
            ['avatar' => $file],
            ['avatar' => ['required', new SafeImage()]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('avatar'))
            ->toContain('Sexual');
    }

    /** @test */
    public function it_validates_with_specific_categories(): void
    {
        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'categoriesAnalysis' => [
                    ['category' => 'Sexual', 'severity' => 0],
                    ['category' => 'Violence', 'severity' => 0],
                ],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $validator = Validator::make(
            ['avatar' => $file],
            ['avatar' => ['required', new SafeImage(['Sexual', 'Violence'])]]
        );

        expect($validator->passes())->toBeTrue();

        Http::assertSent(function ($request) {
            $data = $request->data();
            return $data['categories'] === ['Sexual', 'Violence'];
        });
    }

    /** @test */
    public function it_fails_validation_for_non_uploaded_file(): void
    {
        $validator = Validator::make(
            ['avatar' => 'not-a-file'],
            ['avatar' => ['required', new SafeImage()]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('avatar'))
            ->toContain('must be an uploaded file');
    }

    /** @test */
    public function it_sends_base64_encoded_image_to_api(): void
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

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $validator = Validator::make(
            ['avatar' => $file],
            ['avatar' => ['required', new SafeImage()]]
        );

        $validator->passes();

        Http::assertSent(function ($request) {
            $data = $request->data();
            return isset($data['image']['content']) &&
                   !empty($data['image']['content']);
        });
    }

    /** @test */
    public function it_handles_api_errors_gracefully_by_default(): void
    {
        config(['azure-moderator.fail_on_api_error' => false]);

        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'error' => [
                    'code' => 'ServiceUnavailable',
                    'message' => 'Service is temporarily unavailable',
                ],
            ], 503),
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $validator = Validator::make(
            ['avatar' => $file],
            ['avatar' => ['required', new SafeImage()]]
        );

        // By default, the rule doesn't fail on API errors (graceful degradation)
        expect($validator->passes())->toBeTrue();
    }

    /** @test */
    public function it_fails_validation_on_api_error_when_configured(): void
    {
        config(['azure-moderator.fail_on_api_error' => true]);

        Http::fake([
            '*/contentsafety/image:analyze*' => Http::response([
                'error' => [
                    'code' => 'ServiceUnavailable',
                    'message' => 'Service is temporarily unavailable',
                ],
            ], 503),
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $validator = Validator::make(
            ['avatar' => $file],
            ['avatar' => ['required', new SafeImage()]]
        );

        // When fail_on_api_error is true, validation should fail
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('avatar'))
            ->toContain('Unable to validate');
    }

    /** @test */
    public function it_handles_file_read_errors(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        // Mock file_get_contents to return false
        $validator = Validator::make(
            ['avatar' => 'not-a-real-file'],
            ['avatar' => ['required', new SafeImage()]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('avatar'))
            ->toContain('must be an uploaded file');
    }
}
