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
 * Usage example:
 * ```php
 * use Gowelle\AzureModerator\Facades\AzureModerator;
 *
 * $result = AzureModerator::moderate('Some text', 4.5);
 * ```
 *
 * @method static array moderate(string $text, float $rating, ?array $categories = null)
 *
 * @see \Gowelle\AzureModerator\AzureContentSafetyService
 * @see \Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract
 */
class AzureModerator extends Facade
{
    /**
     * Get the registered name of the component
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'gowelle.azure-moderator';
    }
}