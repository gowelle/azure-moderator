<?php

namespace Gowelle\AzureModerator\Rules;

use Closure;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Gowelle\AzureModerator\ProtectedMaterialService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

/**
 * Laravel validation rule for safe text content
 *
 * This rule validates text content against Azure Content Safety API,
 * checking for both harmful content and protected (copyrighted) material.
 *
 * Usage:
 * ```php
 * $request->validate([
 *     'content' => ['required', new SafeText()],
 * ]);
 * ```
 */
class SafeText implements ValidationRule
{
    protected bool $checkHarmful;

    protected bool $checkProtected;

    protected float $defaultRating;

    /**
     * Create a new rule instance
     *
     * @param  bool  $checkHarmful  Whether to check for harmful content
     * @param  bool  $checkProtected  Whether to check for protected material
     * @param  float  $defaultRating  Default rating to use for harmful content check (0-5)
     */
    public function __construct(
        bool $checkHarmful = true,
        bool $checkProtected = true,
        float $defaultRating = 5.0
    ) {
        $this->checkHarmful = $checkHarmful;
        $this->checkProtected = $checkProtected;
        $this->defaultRating = $defaultRating;
    }

    /**
     * Run the validation rule
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || empty($value)) {
            return;
        }

        try {
            // Check for harmful content
            if ($this->checkHarmful) {
                $result = AzureModerator::moderate($value, $this->defaultRating);

                if ($result['status'] === 'flagged') {
                    $reason = $result['reason'] ?? 'harmful content';
                    $fail("The {$attribute} contains {$reason}.");

                    return;
                }
            }

            // Check for protected material
            if ($this->checkProtected) {
                $service = app(ProtectedMaterialService::class);
                $result = $service->detectProtectedMaterial($value);

                if ($result['detected']) {
                    $fail("The {$attribute} contains protected or copyrighted material.");

                    return;
                }
            }

        } catch (\Exception $e) {
            Log::error('SafeText validation failed', [
                'error' => $e->getMessage(),
                'attribute' => $attribute,
            ]);

            // Fail gracefully - allow content through on API errors
            // unless configured otherwise
            if (config('azure-moderator.fail_on_api_error', false)) {
                $fail("Unable to validate {$attribute} safety. Please try again.");
            }
        }
    }
}
