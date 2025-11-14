<?php

namespace Gowelle\AzureModerator\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Gowelle\AzureModerator\Exceptions\ModerationException;

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
     * @param array|null $categories Optional categories to check, defaults to all
     */
    public function __construct(
        protected ?array $categories = null
    ) {}

    /**
     * Run the validation rule
     *
     * @param string $attribute The attribute being validated
     * @param mixed $value The value being validated (should be UploadedFile)
     * @param Closure $fail The failure callback
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check if value is an uploaded file
        if (!$value instanceof \Illuminate\Http\UploadedFile) {
            $fail('The :attribute must be an uploaded file.');
            return;
        }

        // Check if file is a valid image
        if (!$value->isValid()) {
            $fail('The :attribute file is not valid.');
            return;
        }

        try {
            // Convert image to base64 for moderation
            $imageData = base64_encode(file_get_contents($value->getRealPath()));

            // Moderate the image
            $result = AzureModerator::moderateImage(
                image: $imageData,
                categories: $this->categories,
                encoding: 'base64'
            );

            // Check if image was flagged
            if ($result['status'] === 'flagged') {
                $reason = $result['reason'] ?? 'harmful content';
                $fail("The :attribute contains {$reason} and cannot be accepted.");
            }

        } catch (ModerationException $e) {
            // Log the error but don't fail validation on API errors
            // This prevents blocking users when Azure API is down
            \Illuminate\Support\Facades\Log::warning('Image moderation validation failed', [
                'attribute' => $attribute,
                'error' => $e->getMessage(),
            ]);

            // Optionally fail validation on API errors
            // Uncomment the line below to enforce strict validation
            // $fail('Unable to validate :attribute safety. Please try again.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Unexpected error during image validation', [
                'attribute' => $attribute,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
