<?php

namespace Gowelle\AzureModerator\Rules;

use Closure;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Laravel validation rule for safe image content
 *
 * This rule validates uploaded images using Azure Content Safety API
 * to ensure they don't contain harmful content.
 *
 * Usage example:
 * ```php
 * use Gowelle\AzureModerator\Rules\SafeImage;
 *
 * $request->validate([
 *     'avatar' => ['required', 'image', new SafeImage()],
 * ]);
 *
 * // With custom categories
 * $request->validate([
 *     'avatar' => ['required', 'image', new SafeImage(['Sexual', 'Violence'])],
 * ]);
 * ```
 */
class SafeImage implements ValidationRule
{
    /**
     * Create a new rule instance
     *
     * @param  array<string>|null  $categories  Optional categories to check, defaults to all
     */
    public function __construct(
        protected ?array $categories = null
    ) {}

    /**
     * Run the validation rule
     *
     * @param  string  $attribute  The attribute being validated
     * @param  mixed  $value  The value being validated (should be UploadedFile)
     * @param  Closure  $fail  The failure callback
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check if value is an uploaded file
        if (! $value instanceof \Illuminate\Http\UploadedFile) {
            $fail('The :attribute must be an uploaded file.');

            return;
        }

        // Check if file is a valid image
        if (! $value->isValid()) {
            $fail('The :attribute file is not valid.');

            return;
        }

        try {
            // Convert image to base64 for moderation
            $contents = file_get_contents($value->getRealPath());

            if ($contents === false) {
                $fail('Unable to read :attribute file.');

                return;
            }

            $imageData = base64_encode($contents);

            // Moderate the image
            $result = AzureModerator::moderateImage(
                image: $imageData,
                categories: $this->categories,
                encoding: 'base64'
            );

            // Check if API failed (indicated by empty analysis) and strict mode is enabled
            if (empty($result->categoriesAnalysis) && config('azure-moderator.fail_on_api_error', false)) {
                \Illuminate\Support\Facades\Log::warning('Image moderation API unavailable', [
                    'attribute' => $attribute,
                ]);
                $fail('Unable to validate :attribute safety. Please try again.');

                return;
            }

            // Check if image was flagged
            if ($result->isFlagged()) {
                $reason = $result->reason ?? 'harmful content';
                $fail("The :attribute contains {$reason} and cannot be accepted.");
            }

        } catch (ModerationException $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::warning('Image moderation validation failed', [
                'attribute' => $attribute,
                'error' => $e->getMessage(),
            ]);

            // Check if we should fail validation on API errors
            if (config('azure-moderator.fail_on_api_error', false)) {
                $fail('Unable to validate :attribute safety. Please try again.');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Unexpected error during image validation', [
                'attribute' => $attribute,
                'error' => $e->getMessage(),
            ]);

            // Fail validation on unexpected errors
            if (config('azure-moderator.fail_on_api_error', false)) {
                $fail('Unable to validate :attribute safety. Please try again.');
            }
        }
    }
}
