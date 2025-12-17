<?php

namespace Gowelle\AzureModerator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for Azure Content Safety moderation service
 *
 * This facade provides a static interface to the Azure Content Safety service,
 * allowing for easy access to content moderation functionality throughout your
 * Laravel application.
 *
 * Usage examples:
 * ```php
 * use Gowelle\AzureModerator\Facades\AzureModerator;
 *
 * // Moderate text
 * $result = AzureModerator::moderate('Some text', 4.5);
 *
 * // Moderate image by URL
 * $imageResult = AzureModerator::moderateImage('https://example.com/image.jpg');
 *
 * // Moderate base64 image
 * $base64Result = AzureModerator::moderateImage($base64Data, encoding: 'base64');
 * ```
 *
 * @method static \Gowelle\AzureModerator\Data\ModerationResult moderate(string $text, float $rating, ?array $categories = null, ?array $blocklistNames = null, bool $haltOnBlocklistHit = false)
 * @method static \Gowelle\AzureModerator\Data\ModerationResult moderateImage(string $image, ?array $categories = null, string $encoding = 'url')
 * @method static array<int, \Gowelle\AzureModerator\Data\ModerationResult> moderateBatch(array $items)
 * @method static array<string, \Gowelle\AzureModerator\Data\ModerationResult> moderateWithContext(string $text, float $rating, ?string $imageUrl = null, ?array $categories = null, ?array $blocklistNames = null)
 *
 * @see \Gowelle\AzureModerator\AzureContentSafetyService
 * @see \Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract
 */
class AzureModerator extends Facade
{
    /**
     * Get the registered name of the component
     */
    protected static function getFacadeAccessor(): string
    {
        return \Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract::class;
    }
}
