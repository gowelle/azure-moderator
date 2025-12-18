<?php

namespace Gowelle\AzureModerator\Rules;

use Closure;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\MultimodalService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

/**
 * Laravel validation rule for safe multimodal content (Preview API)
 *
 * This rule validates uploaded images with optional associated text using
 * the Azure Content Safety Multimodal API to ensure combined content
 * doesn't contain harmful material.
 *
 * Usage example:
 * ```php
 * use Gowelle\AzureModerator\Rules\SafeMultimodal;
 *
 * $request->validate([
 *     'image' => ['required', 'image', new SafeMultimodal()],
 * ]);
 *
 * // With associated text
 * $request->validate([
 *     'image' => ['required', 'image', new SafeMultimodal(text: $request->description)],
 * ]);
 *
 * // With custom categories
 * $request->validate([
 *     'image' => ['required', 'image', new SafeMultimodal(
 *         text: $request->caption,
 *         categories: ['Sexual', 'Violence']
 *     )],
 * ]);
 * ```
 */
class SafeMultimodal implements ValidationRule
{
    /**
     * Create a new rule instance
     *
     * @param  string|null  $text  Optional text to analyze with image
     * @param  array<string>|null  $categories  Optional categories to check
     * @param  bool  $enableOcr  Whether to extract text from image
     */
    public function __construct(
        protected ?string $text = null,
        protected ?array $categories = null,
        protected bool $enableOcr = true
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

        // Check if file is valid
        if (! $value->isValid()) {
            $fail('The :attribute file is not valid.');

            return;
        }

        try {
            // Convert image to base64
            $contents = file_get_contents($value->getRealPath());

            if ($contents === false) {
                $fail('Unable to read :attribute file.');

                return;
            }

            $imageData = base64_encode($contents);

            // Analyze with multimodal service
            /** @var MultimodalService $service */
            $service = app(MultimodalService::class);

            $result = $service->analyze(
                image: $imageData,
                text: $this->text,
                encoding: 'base64',
                categories: $this->categories,
                enableOcr: $this->enableOcr
            );

            // Check if API failed and strict mode is enabled
            if (empty($result->categoriesAnalysis) && config('azure-moderator.fail_on_api_error', false)) {
                Log::warning('Multimodal moderation API unavailable', [
                    'attribute' => $attribute,
                ]);
                $fail('Unable to validate :attribute safety. Please try again.');

                return;
            }

            // Check if content was flagged
            if ($result->isFlagged()) {
                $reason = $result->reason ?? 'harmful content';
                $fail("The :attribute contains {$reason} and cannot be accepted.");
            }

        } catch (ModerationException $e) {
            Log::warning('Multimodal validation failed', [
                'attribute' => $attribute,
                'error' => $e->getMessage(),
            ]);

            $failOnError = (bool) config('azure-moderator.fail_on_api_error', false);
            if ($failOnError) {
                $fail('Unable to validate :attribute safety. Please try again.');
            }
        } catch (\Exception $e) {
            Log::error('Unexpected error during multimodal validation', [
                'attribute' => $attribute,
                'error' => $e->getMessage(),
            ]);

            $failOnError = (bool) config('azure-moderator.fail_on_api_error', false);
            /** @phpstan-ignore-next-line */
            if ($failOnError) {
                $fail('Unable to validate :attribute safety. Please try again.');
            }
        }
    }
}
