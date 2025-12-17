<?php

namespace Gowelle\AzureModerator;

use Gowelle\AzureModerator\Data\BlocklistMatch;
use Gowelle\AzureModerator\Data\CategoryAnalysis;
use Gowelle\AzureModerator\Data\ModerationResult;
use Gowelle\AzureModerator\Data\ModeratorConfig;
use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Enums\ModerationStatus;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Azure Content Safety Service for content moderation
 *
 * This service integrates with Azure's Content Safety API to analyze text content
 * and determine if it should be approved or flagged based on content analysis
 * and user ratings.
 *
 * @see https://learn.microsoft.com/en-us/azure/ai-services/content-safety/overview
 */
class AzureContentSafetyService implements \Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract
{
    private const API_VERSION = '2024-09-01';

    private const RETRY_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 100;

    private const RETRY_STATUS_CODES = [429, 500, 503];

    protected ModeratorConfig $config;

    public function __construct()
    {
        $this->config = ModeratorConfig::fromConfig();
    }

    /**
     * Moderate content using Azure Content Safety API
     *
     * This method analyzes text content for potentially harmful content and
     * combines it with a user rating to determine if the content should be
     * approved or flagged.
     *
     * @param  string  $text  The text content to analyze
     * @param  float  $rating  User rating (0-5)
     * @param  array<string>|null  $categories  Optional categories to analyze, defaults to all
     * @param  array<string>|null  $blocklistNames  Optional blocklist names to check against
     * @param  bool  $haltOnBlocklistHit  Whether to halt analysis on blocklist match
     * @return array{status: string, reason: string|null, blocklistMatches: array|null} Moderation result
     */
    public function moderate(
        string $text,
        float $rating,
        ?array $categories = null,
        ?array $blocklistNames = null,
        bool $haltOnBlocklistHit = false
    ): ModerationResult {
        try {
            $this->validateRequest($text, $rating);

            $response = $this->makeApiRequest(
                text: $text,
                categories: $categories ?? ContentCategory::defaultCategories(),
                blocklistNames: $blocklistNames,
                haltOnBlocklistHit: $haltOnBlocklistHit
            );

            $responseData = $response->json();
            $apiScores = $responseData['categoriesAnalysis'] ?? [];
            $apiBlocklistMatches = $responseData['blocklistsMatch'] ?? [];

            // Hydrate DTOs
            $categoriesAnalysis = array_map(fn ($score) => new CategoryAnalysis(
                category: ContentCategory::from($score['category']),
                severity: $score['severity']
            ), $apiScores);

            $blocklistMatches = array_map(fn ($match) => new BlocklistMatch(
                blocklistName: $match['blocklistName'],
                matchId: $match['blocklistMatchId'],
                matchValue: $match['blocklistMatchValue']
            ), $apiBlocklistMatches);

            $analysis = $this->analyzeScores($categoriesAnalysis);

            // Check if blocklist was matched
            $hasBlocklistMatch = ! empty($blocklistMatches);

            if (! $analysis['hasHighRisk'] && ! $hasBlocklistMatch && $rating >= $this->config->lowRatingThreshold) {
                return new ModerationResult(
                    status: ModerationStatus::APPROVED,
                    trackingId: $responseData['trackingId'] ?? null,
                    categoriesAnalysis: $categoriesAnalysis,
                    blocklistMatches: $blocklistMatches
                );
            }

            $reason = $analysis['reason'];
            if ($hasBlocklistMatch) {
                $reason = $reason ? $reason.', blocklist_match' : 'blocklist_match';
            } elseif (! $reason) {
                $reason = 'low_rating';
            }

            return new ModerationResult(
                status: ModerationStatus::FLAGGED,
                reason: $reason,
                trackingId: $responseData['trackingId'] ?? null,
                categoriesAnalysis: $categoriesAnalysis,
                blocklistMatches: $blocklistMatches
            );

        } catch (\Exception $e) {
            Log::error('Azure moderation failed', [
                'error' => $e->getMessage(),
                'endpoint' => $this->config->endpoint,
                'text_length' => strlen($text),
                'rating' => $rating,
            ]);

            return new ModerationResult(
                status: $rating >= $this->config->lowRatingThreshold
                    ? ModerationStatus::APPROVED
                    : ModerationStatus::FLAGGED,
                reason: $rating >= $this->config->lowRatingThreshold ? null : 'low_rating',
            );
        }
    }

    /**
     * Make an API request to Azure Content Safety
     *
     * Handles retrying of failed requests and proper error handling.
     *
     * @param  string  $text  Content to analyze
     * @param  array<string>  $categories  Categories to check
     * @param  array<string>|null  $blocklistNames  Optional blocklist names
     * @param  bool  $haltOnBlocklistHit  Whether to halt on blocklist match
     */
    protected function makeApiRequest(
        string $text,
        array $categories,
        ?array $blocklistNames = null,
        bool $haltOnBlocklistHit = false
    ): Response {
        $endpoint = rtrim($this->config->endpoint, '/')
            .'/contentsafety/text:analyze?api-version='.self::API_VERSION;

        $payload = [
            'text' => $text,
            'categories' => $categories,
        ];

        // Add blocklist parameters if provided
        if ($blocklistNames !== null && count($blocklistNames) > 0) {
            $payload['blocklistNames'] = $blocklistNames;
            $payload['haltOnBlocklistHit'] = $haltOnBlocklistHit;
        }

        try {
            $response = Http::retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                return $exception instanceof RequestException &&
                       in_array($exception->response->status(), self::RETRY_STATUS_CODES);
            })->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->config->apiKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            $this->logApiResponse($response);

            if ($response->failed()) {
                throw new ModerationException(
                    message: $this->getErrorMessage($response),
                    endpoint: $this->config->endpoint,
                    statusCode: $response->status()
                );
            }

            return $response;

        } catch (\Exception $e) {
            throw new ModerationException(
                message: 'Failed to connect to Azure API',
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Log failed API responses
     *
     * @param  Response  $response  The API response
     */
    protected function logApiResponse(Response $response): void
    {
        if ($response->failed()) {
            Log::warning('Azure API request failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'endpoint' => $this->config->endpoint,
            ]);
        }
    }

    /**
     * Format error message from API response
     *
     * @param  Response  $response  The API response
     * @return string Formatted error message
     */
    protected function getErrorMessage(Response $response): string
    {
        $body = $response->json();
        $error = $body['error'] ?? [];

        return sprintf(
            'Azure API request failed (HTTP %d): [%s] %s',
            $response->status(),
            $error['code'] ?? 'unknown',
            $error['message'] ?? $response->body()
        );
    }

    /**
     * Validate request parameters
     *
     * @param  string  $text  Content to validate
     * @param  float  $rating  Rating to validate
     */
    protected function validateRequest(string $text, float $rating): void
    {
        if (empty($text)) {
            throw new \InvalidArgumentException('Text cannot be empty');
        }

        if ($rating < 0 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 0 and 5');
        }
    }

    /**
     * Moderate image content using Azure Content Safety API
     *
     * This method analyzes image content for potentially harmful content.
     * Supports both URL and base64-encoded images.
     *
     * On API failures, returns an approved status by default (graceful degradation).
     * This behavior ensures users aren't blocked during Azure API outages.
     *
     * @param  string  $image  Either a URL to the image or base64-encoded image data
     * @param  array<string>|null  $categories  Optional categories to analyze, defaults to all
     * @param  string  $encoding  Either 'url' (default) or 'base64' to indicate image format
     * @return array{status: string, reason: string|null, scores: array<array{category: string, severity: int}>|null} Moderation result
     */
    public function moderateImage(
        string $image,
        ?array $categories = null,
        string $encoding = 'url'
    ): ModerationResult {
        // Validate input - these exceptions should be thrown, not caught
        $this->validateImageRequest($image, $encoding);

        try {
            $response = $this->makeImageApiRequest(
                image: $image,
                encoding: $encoding,
                categories: $categories ?? ContentCategory::defaultCategories()
            );

            $responseData = $response->json();
            $apiScores = $responseData['categoriesAnalysis'] ?? [];

            // Hydrate DTOs
            $categoriesAnalysis = array_map(fn ($score) => new CategoryAnalysis(
                category: ContentCategory::from($score['category']),
                severity: $score['severity']
            ), $apiScores);

            $analysis = $this->analyzeScores($categoriesAnalysis);

            return new ModerationResult(
                status: $analysis['hasHighRisk'] ? ModerationStatus::FLAGGED : ModerationStatus::APPROVED,
                reason: $analysis['reason'] ?: null,
                trackingId: $responseData['trackingId'] ?? null,
                categoriesAnalysis: $categoriesAnalysis
            );

        } catch (\Exception $e) {
            Log::error('Azure image moderation failed', [
                'error' => $e->getMessage(),
                'endpoint' => $this->config->endpoint,
                'encoding' => $encoding,
            ]);

            // Return approved status on API failure (graceful degradation)
            return new ModerationResult(status: ModerationStatus::APPROVED);
        }
    }

    /**
     * Make an API request to Azure Content Safety for image analysis
     *
     * @param  string  $image  Image URL or base64 data
     * @param  string  $encoding  Image encoding type
     * @param  array<string>  $categories  Categories to check
     */
    protected function makeImageApiRequest(string $image, string $encoding, array $categories): Response
    {
        $endpoint = rtrim($this->config->endpoint, '/')
            .'/contentsafety/image:analyze?api-version='.self::API_VERSION;

        $payload = [
            'categories' => $categories,
        ];

        if ($encoding === 'url') {
            $payload['image'] = ['url' => $image];
        } else {
            $payload['image'] = ['content' => $image];
        }

        try {
            $response = Http::retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                return $exception instanceof RequestException &&
                       in_array($exception->response->status(), self::RETRY_STATUS_CODES);
            })->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->config->apiKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            $this->logApiResponse($response);

            if ($response->failed()) {
                throw new ModerationException(
                    message: $this->getErrorMessage($response),
                    endpoint: $this->config->endpoint,
                    statusCode: $response->status()
                );
            }

            return $response;

        } catch (\Exception $e) {
            throw new ModerationException(
                message: 'Failed to connect to Azure API for image analysis',
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Validate image request parameters
     *
     * @param  string  $image  Image to validate
     * @param  string  $encoding  Encoding type
     */
    protected function validateImageRequest(string $image, string $encoding): void
    {
        if (empty($image)) {
            throw new \InvalidArgumentException('Image cannot be empty');
        }

        if (! in_array($encoding, ['url', 'base64'])) {
            throw new \InvalidArgumentException('Encoding must be either "url" or "base64"');
        }

        if ($encoding === 'url' && ! filter_var($image, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid image URL provided');
        }

        // Validate base64 length (Azure has limits)
        // Note: This is the base64 string length limit, not the original image size.
        // Base64 encoding increases size by ~33%, so this limits original images to ~3MB.
        if ($encoding === 'base64' && strlen($image) > 4194304) { // 4MB limit for base64 string
            throw new \InvalidArgumentException('Base64 image data exceeds maximum size of 4MB (approximately 3MB original image size)');
        }
    }

    /**
     * Analyze content safety scores
     *
     * Determines if content has high risk based on severity thresholds
     * and provides reason for flagging if applicable.
     *
     * @param  CategoryAnalysis[]  $scores  Category scores from API
     * @return array{hasHighRisk: bool, reason: string} Analysis result
     */
    protected function analyzeScores(array $scores): array
    {
        $hasHighRisk = collect($scores)->contains(function ($item) {
            return $item->severity >= $this->config->highSeverityThreshold;
        });

        $reason = collect($scores)
            ->filter(fn ($item) => $item->severity >= $this->config->highSeverityThreshold)
            ->map(fn ($item) => $item->category->value)
            ->implode(', ');

        return [
            'hasHighRisk' => $hasHighRisk,
            'reason' => $reason,
        ];
    }

    /**
     * Moderate multiple items in batch
     *
     * Processes multiple text and/or image items concurrently for better performance.
     * Each item should specify its type and relevant parameters.
     *
     * @param  array<array{type: string, content: string, rating?: float, categories?: array<string>, blocklistNames?: array<string>, encoding?: string}>  $items
     * @return array<array{status: string, reason: string|null, index: int, type: string}> Results for each item
     */
    public function moderateBatch(array $items): array
    {
        $results = [];

        foreach ($items as $index => $item) {
            try {
                $type = $item['type'] ?? 'text';

                $result = match ($type) {
                    'text' => $this->moderate(
                        text: $item['content'],
                        rating: $item['rating'] ?? 5.0,
                        categories: $item['categories'] ?? null,
                        blocklistNames: $item['blocklistNames'] ?? null
                    ),
                    'image' => $this->moderateImage(
                        image: $item['content'],
                        categories: $item['categories'] ?? null,
                        encoding: $item['encoding'] ?? 'url'
                    ),
                    default => throw new \InvalidArgumentException("Invalid item type: {$type}"),
                };

                $results[] = $result;

            } catch (\Exception $e) {
                Log::error('Batch moderation item failed', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);

                // Return failed/approved result for graceful degradation
                $results[] = new ModerationResult(status: ModerationStatus::APPROVED, reason: 'batch_error');
            }
        }

        return $results;
    }

    /**
     * Moderate text with optional image context
     *
     * Analyzes text content with an optional related image for better context.
     * This is useful when text and image are related (e.g., a post with an image).
     *
     * @param  string  $text  Text content to analyze
     * @param  float  $rating  User rating (0-5)
     * @param  string|null  $imageUrl  Optional image URL for context
     * @param  array<string>|null  $categories  Optional categories to analyze
     * @param  array<string>|null  $blocklistNames  Optional blocklist names
     * @return array{text: array, image: array|null, combined: array{status: string, reason: string|null}} Combined moderation result
     */
    public function moderateWithContext(
        string $text,
        float $rating,
        ?string $imageUrl = null,
        ?array $categories = null,
        ?array $blocklistNames = null
    ): array {
        $textResult = $this->moderate(
            text: $text,
            rating: $rating,
            categories: $categories,
            blocklistNames: $blocklistNames
        );

        $imageResult = null;
        if ($imageUrl !== null) {
            $imageResult = $this->moderateImage(
                image: $imageUrl,
                categories: $categories
            );
        }

        // Determine combined status - flagged if either is flagged
        $isFlagged = $textResult->isFlagged() || ($imageResult && $imageResult->isFlagged());
        
        $reasons = [];
        if ($textResult->isFlagged() && $textResult->reason) {
            $reasons[] = "Text: {$textResult->reason}";
        }
        if ($imageResult && $imageResult->isFlagged() && $imageResult->reason) {
            $reasons[] = "Image: {$imageResult->reason}";
        }

        $combinedResult = new ModerationResult(
            status: $isFlagged ? ModerationStatus::FLAGGED : ModerationStatus::APPROVED,
            reason: $isFlagged ? implode(', ', $reasons) : null
        );

        return [
            'text' => $textResult,
            'image' => $imageResult,
            'combined' => $combinedResult,
        ];
    }
}
