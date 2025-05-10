<?php

namespace Gowelle\AzureModerator\Facades;

use Illuminate\Support\Facades\Facade;


/**
 * @method static \Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract moderate(string $text, float $rating): array
 *
 * @see \Gowelle\AzureModerator\AzureContentSafetyService
 */
class AzureModerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'gowelle.azure-moderator';
    }
}