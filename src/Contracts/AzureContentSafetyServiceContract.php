<?php

namespace Gowelle\AzureModerator\Contracts;

/**
 * Contract for Azure Content Safety moderation service
 *
 * This interface defines the contract for content moderation services using
 * Azure Content Safety API. Implementations should handle the analysis of text
 * content for potentially harmful content and combine it with user ratings to
 * determine if content should be approved or flagged.
 *
 * Usage example:
 * ```php
 * $service = app(AzureContentSafetyServiceContract::class);
 * $result = $service->moderate('Some text', 4.5);
 * ```
 *
 * @see \Gowelle\AzureModerator\AzureContentSafetyService
 * @see https://learn.microsoft.com/en-us/azure/ai-services/content-safety/overview
 */
interface AzureContentSafetyServiceContract
{
    /**
     * Moderate content using Azure Content Safety API
     *
     * This method analyzes text content for potentially harmful content and
     * combines it with a user rating to determine if the content should be
     * approved or flagged for review.
     *
     * @param string $text The text content to analyze (must not be empty)
     * @param float $rating User rating between 0 and 5 (inclusive)
     * @param array|null $categories Optional categories to analyze, defaults to all categories
     * @return array{status: string, reason: string|null} Returns an array with moderation status and optional reason
     * @throws \Gowelle\AzureModerator\Exceptions\ModerationException When API request fails
     * @throws \InvalidArgumentException When input validation fails
     *
     * Example response:
     * ```php
     * [
     *     'status' => 'approved',
     *     'reason' => null
     * ]
     * ```
     */
    public function moderate(
        string $text, 
        float $rating, 
        ?array $categories = null
    ): array;
}