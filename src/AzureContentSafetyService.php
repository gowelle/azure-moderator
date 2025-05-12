<?php

namespace Gowelle\AzureModerator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Gowelle\AzureModerator\Data\ModeratorConfig;
use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Enums\ModerationStatus;
use Gowelle\AzureModerator\Data\ModerationResult;
use Illuminate\Http\Client\RequestException;
use Gowelle\AzureModerator\Exceptions\ModerationException;

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
     * @param string $text The text content to analyze
     * @param float $rating User rating (0-5)
     * @param array|null $categories Optional categories to analyze, defaults to all
     * @return array{status: string, reason: string|null} Moderation result
     * @throws InvalidArgumentException When input validation fails
     * @throws ModerationException When API request fails
     */
    public function moderate(
        string $text, 
        float $rating, 
        ?array $categories = null
    ): array {
        try {
            $this->validateRequest($text, $rating);
            
            $response = $this->makeApiRequest(
                text: $text, 
                categories: $categories ?? ContentCategory::defaultCategories()
            );
            
            $scores = $response->json()['categoriesAnalysis'] ?? [];
            
            $analysis = $this->analyzeScores($scores);
            
            if (!$analysis['hasHighRisk'] && $rating >= $this->config->lowRatingThreshold) {
                return (new ModerationResult(
                    status: ModerationStatus::APPROVED,
                ))->toArray();
            }

            return (new ModerationResult(
                status: ModerationStatus::FLAGGED,
                reason: $analysis['reason'] ?: 'low_rating'
            ))->toArray();

        } catch (\Exception $e) {
            Log::error('Azure moderation failed', [
                'error' => $e->getMessage(),
                'endpoint' => $this->config->endpoint,
                'text_length' => strlen($text),
                'rating' => $rating
            ]);

            return (new ModerationResult(
                status: $rating >= $this->config->lowRatingThreshold 
                    ? ModerationStatus::APPROVED 
                    : ModerationStatus::FLAGGED,
                reason: $rating >= $this->config->lowRatingThreshold ? null : 'low_rating'
            ))->toArray();
        }
    }

    /**
     * Make an API request to Azure Content Safety
     *
     * Handles retrying of failed requests and proper error handling.
     *
     * @param string $text Content to analyze
     * @param array $categories Categories to check
     * @return Response
     * @throws ModerationException When request fails
     */
    protected function makeApiRequest(string $text, array $categories): Response
    {
        $endpoint = rtrim($this->config->endpoint, '/') 
            . '/contentsafety/text:analyze?api-version=' . self::API_VERSION;
        
        try {
            $response = Http::retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                return $exception instanceof RequestException && 
                       in_array($exception->response->status(), self::RETRY_STATUS_CODES);
            })->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->config->apiKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'text' => $text,
                'categories' => $categories,
            ]);

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
     * @param Response $response The API response
     */
    protected function logApiResponse(Response $response): void
    {
        if ($response->failed()) {
            Log::warning('Azure API request failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'endpoint' => $this->config->endpoint
            ]);
        }
    }

    /**
     * Format error message from API response
     *
     * @param Response $response The API response
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
     * @param string $text Content to validate
     * @param float $rating Rating to validate
     * @throws InvalidArgumentException When validation fails
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
     * Analyze content safety scores
     *
     * Determines if content has high risk based on severity thresholds
     * and provides reason for flagging if applicable.
     *
     * @param array<array{category: string, severity: int}> $scores Category scores from API
     * @return array{hasHighRisk: bool, reason: string} Analysis result
     */
    protected function analyzeScores(array $scores): array
    {
        $hasHighRisk = collect($scores)->contains(function ($item) {
            return $item['severity'] >= $this->config->highSeverityThreshold;
        });

        $reason = collect($scores)
            ->filter(fn ($item) => $item['severity'] >= $this->config->highSeverityThreshold)
            ->pluck('category')
            ->implode(', ');

        return [
            'hasHighRisk' => $hasHighRisk,
            'reason' => $reason,
        ];
    }
}