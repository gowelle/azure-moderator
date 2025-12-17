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
     * @param  array<string>|null  $blocklistNames  Optional blocklist names to check against
     * @param  bool  $haltOnBlocklistHit  Whether to halt analysis on blocklist match
     * ```
     */
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
     * @param  array<string>|null  $blocklistNames  Optional blocklist names to check against
     * @param  bool  $haltOnBlocklistHit  Whether to halt analysis on blocklist match
     * @return \Gowelle\AzureModerator\Data\ModerationResult Returns a moderation result DTO
     *
     * @throws \Gowelle\AzureModerator\Exceptions\ModerationException When API request fails
     * @throws \InvalidArgumentException When input validation fails
     *
     * Example response:
     * ```php
     * new ModerationResult(status: ModerationStatus::APPROVED)
     * ```
     */
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
     * @param  array<string>|null  $blocklistNames  Optional blocklist names to check against
     * @param  bool  $haltOnBlocklistHit  Whether to halt analysis on blocklist match
     * @return \Gowelle\AzureModerator\Data\ModerationResult Returns a moderation result DTO
     *
     * @throws \Gowelle\AzureModerator\Exceptions\ModerationException When API request fails
     * @throws \InvalidArgumentException When input validation fails
     */
    public function moderate(
        string $text,
        float $rating,
        ?array $categories = null,
        ?array $blocklistNames = null,
        bool $haltOnBlocklistHit = false
    ): \Gowelle\AzureModerator\Data\ModerationResult;

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
     * @return \Gowelle\AzureModerator\Data\ModerationResult Returns a moderation result DTO
     *
     * @throws \InvalidArgumentException When input validation fails
     */
    public function moderateImage(
        string $image,
        ?array $categories = null,
        string $encoding = 'url'
    ): \Gowelle\AzureModerator\Data\ModerationResult;

    /**
     * Moderate a batch of content items
     *
     * @param  array<array{type: string, content: string, rating?: float, categories?: array<string>, blocklistNames?: array<string>, encoding?: string}>  $items
     * @return array<int, \Gowelle\AzureModerator\Data\ModerationResult>
     */
    public function moderateBatch(array $items): array;

    /**
     * Moderate text content with image context (Multi-modal)
     * 
     * @param  string  $text  The text content to analyze
     * @param  float  $rating  User rating
     * @param  string|null  $imageUrl  Optional image URL
     * @param  array<string>|null  $categories  Optional categories to analyze
     * @param  array<string>|null  $blocklistNames  Optional blocklist names
     * @return array{text: \Gowelle\AzureModerator\Data\ModerationResult, image: \Gowelle\AzureModerator\Data\ModerationResult|null, combined: \Gowelle\AzureModerator\Data\ModerationResult} Keyed by 'text' and 'image'
     */
    public function moderateWithContext(
        string $text,
        float $rating,
        ?string $imageUrl = null,
        ?array $categories = null,
        ?array $blocklistNames = null
    ): array;
}
