<?php

namespace Gowelle\AzureModerator\Contracts;

/**
 * Contract for Azure Content Safety moderation service
 *
 * This interface defines the contract for content moderation services using
 * Azure Content Safety API. Implementations should handle the analysis of text
 * and image content for potentially harmful content.
 *
 * Usage example:
 * ```php
 * $service = app(AzureContentSafetyServiceContract::class);
 * $result = $service->moderate('Some text', 4.5);
 * $imageResult = $service->moderateImage('https://example.com/image.jpg');
 * ```
 *
 * @see \Gowelle\AzureModerator\AzureContentSafetyService
 * @see https://learn.microsoft.com/en-us/azure/ai-services/content-safety/overview
 */
interface AzureContentSafetyServiceContract
{
    /**
     * Moderate text content using Azure Content Safety API
     *
     * This method analyzes text content for potentially harmful content and
     * combines it with a user rating to determine if the content should be
     * approved or flagged for review.
     *
     * @param  string  $text  The text content to analyze (must not be empty)
     * @param  float  $rating  User rating between 0 and 5 (inclusive)
     * @param  array<string>|null  $categories  Optional categories to analyze, defaults to all categories
     * @return array{status: string, reason: string|null} Returns an array with moderation status and optional reason
     *
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

    /**
     * Moderate image content using Azure Content Safety API
     *
     * This method analyzes image content for potentially harmful content.
     * Supports both URL and base64-encoded images.
     *
     * On API failures, returns an approved status by default (graceful degradation).
     * This ensures users aren't blocked during Azure API outages.
     *
     * @param  string  $image  Either a URL to the image or base64-encoded image data
     * @param  array<string>|null  $categories  Optional categories to analyze, defaults to all categories
     * @param  string  $encoding  Either 'url' (default) or 'base64' to indicate image format
     * @return array{status: string, reason: string|null, scores: array<array{category: string, severity: int}>|null} Returns an array with moderation status, optional reason, and severity scores
     *
     * @throws \InvalidArgumentException When input validation fails
     *
     * Example response (success):
     * ```php
     * [
     *     'status' => 'flagged',
     *     'reason' => 'Violence',
     *     'scores' => [
     *         ['category' => 'Violence', 'severity' => 6],
     *         ['category' => 'Hate', 'severity' => 0]
     *     ]
     * ]
     * ```
     *
     * Example response (API failure):
     * ```php
     * [
     *     'status' => 'approved',
     *     'reason' => null,
     *     'scores' => null
     * ]
     * ```
     */
    public function moderateImage(
        string $image,
        ?array $categories = null,
        string $encoding = 'url'
    ): array;
}
