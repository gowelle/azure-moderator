<?php

namespace Gowelle\AzureModerator;

use Gowelle\AzureModerator\Data\CategoryAnalysis;
use Gowelle\AzureModerator\Data\ModeratorConfig;
use Gowelle\AzureModerator\Data\MultimodalResult;
use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Enums\ModerationStatus;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Azure Content Safety Multimodal Service (Preview API)
 *
 * This service analyzes combined text and image content for harmful material
 * using Azure Content Safety Multimodal API. It provides contextual analysis
 * by considering both text and image together.
 *
 * NOTE: This API uses the 2024-09-15-preview version and may change.
 *
 * @see https://learn.microsoft.com/en-us/azure/ai-services/content-safety/quickstart-multimodal
 */
class MultimodalService
{
    private const API_VERSION = '2024-09-15-preview';

    private const RETRY_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 100;

    private const RETRY_STATUS_CODES = [429, 500, 503];

    protected ModeratorConfig $config;

    public function __construct()
    {
        $this->config = ModeratorConfig::fromConfig();
    }

    /**
     * Analyze combined image and text content
     *
     * This method processes an image along with optional text content to detect
     * harmful material. The combined analysis provides better context awareness
     * than analyzing image and text separately.
     *
     * @param  string  $image  Image data (base64 encoded) or URL
     * @param  string|null  $text  Optional text content to analyze with image
     * @param  string  $encoding  Image encoding type: 'base64' or 'url'
     * @param  array<string>|null  $categories  Categories to check (defaults to all)
     * @param  bool  $enableOcr  Whether to extract and analyze text from image
     * @return MultimodalResult Analysis result
     *
     * @throws ModerationException
     */
    public function analyze(
        string $image,
        ?string $text = null,
        string $encoding = 'base64',
        ?array $categories = null,
        bool $enableOcr = true
    ): MultimodalResult {
        $this->validateRequest($image, $encoding);

        $categories = $categories ?? ContentCategory::defaultCategories();

        try {
            $response = $this->makeApiRequest($image, $encoding, $categories, $text, $enableOcr);
            $result = $response->json();

            // Parse category scores
            $scores = $this->parseCategoryScores($result['categoriesAnalysis'] ?? []);

            // Analyze for high-risk content
            $analysis = $this->analyzeScores($scores);

            return new MultimodalResult(
                status: $analysis['hasHighRisk'] ? ModerationStatus::FLAGGED : ModerationStatus::APPROVED,
                reason: $analysis['hasHighRisk'] ? $analysis['reason'] : null,
                categoriesAnalysis: $scores,
            );

        } catch (ModerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Multimodal analysis failed: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Make API request to Azure Content Safety Multimodal endpoint
     *
     * @param  string  $image  Image data or URL
     * @param  string  $encoding  Image encoding type
     * @param  array<string>  $categories  Categories to check
     * @param  string|null  $text  Optional text content
     * @param  bool  $enableOcr  Whether to enable OCR
     */
    protected function makeApiRequest(
        string $image,
        string $encoding,
        array $categories,
        ?string $text,
        bool $enableOcr
    ): Response {
        $endpoint = rtrim($this->config->endpoint, '/')
            .'/contentsafety/imageWithText:analyze?api-version='.self::API_VERSION;

        // Build image payload based on encoding type
        $imagePayload = $encoding === 'url'
            ? ['blobUrl' => $image]
            : ['content' => $image];

        $payload = [
            'image' => $imagePayload,
            'categories' => $categories,
            'enableOcr' => $enableOcr,
        ];

        // Add text if provided
        if ($text !== null && $text !== '') {
            $payload['text'] = $text;
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
            if ($e instanceof ModerationException) {
                throw $e;
            }

            // Try to extract error message from RequestException
            $errorMessage = 'Failed to connect to Azure Multimodal API';
            if ($e instanceof RequestException && property_exists($e, 'response') && $e->response !== null) {
                $body = $e->response->json();
                $error = $body['error'] ?? [];
                $errorMessage = sprintf(
                    'Azure Multimodal API error (HTTP %d): [%s] %s',
                    $e->response->status(),
                    $error['code'] ?? 'unknown',
                    $error['message'] ?? $e->getMessage()
                );
            } elseif ($e->getPrevious() instanceof RequestException) {
                $prev = $e->getPrevious();
                if (property_exists($prev, 'response') && $prev->response !== null) {
                    $body = $prev->response->json();
                    $error = $body['error'] ?? [];
                    $errorMessage = sprintf(
                        'Azure Multimodal API error (HTTP %d): [%s] %s',
                        $prev->response->status(),
                        $error['code'] ?? 'unknown',
                        $error['message'] ?? $e->getMessage()
                    );
                }
            }

            throw new ModerationException(
                message: $errorMessage,
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Parse category scores from API response
     *
     * @param  array<array{category: string, severity: int}>  $rawScores
     * @return CategoryAnalysis[]
     */
    protected function parseCategoryScores(array $rawScores): array
    {
        $scores = [];
        foreach ($rawScores as $score) {
            $category = ContentCategory::tryFrom($score['category'] ?? '');
            if ($category !== null) {
                $scores[] = new CategoryAnalysis(
                    category: $category,
                    severity: $score['severity'] ?? 0
                );
            }
        }
        return $scores;
    }

    /**
     * Analyze scores for high-risk content
     *
     * @param  CategoryAnalysis[]  $scores
     * @return array{hasHighRisk: bool, reason: string}
     */
    protected function analyzeScores(array $scores): array
    {
        $threshold = $this->config->highSeverityThreshold;
        $flaggedCategories = [];

        foreach ($scores as $score) {
            if ($score->severity >= $threshold) {
                $flaggedCategories[] = $score->category->value;
            }
        }

        if (count($flaggedCategories) > 0) {
            return [
                'hasHighRisk' => true,
                'reason' => 'High severity in: '.implode(', ', $flaggedCategories),
            ];
        }

        return [
            'hasHighRisk' => false,
            'reason' => '',
        ];
    }

    /**
     * Validate request parameters
     *
     * @param  string  $image  Image to validate
     * @param  string  $encoding  Encoding type
     */
    protected function validateRequest(string $image, string $encoding): void
    {
        if (empty($image)) {
            throw new \InvalidArgumentException('Image cannot be empty');
        }

        if (! in_array($encoding, ['base64', 'url'])) {
            throw new \InvalidArgumentException('Encoding must be "base64" or "url"');
        }

        // Validate URL format if URL encoding
        if ($encoding === 'url' && ! filter_var($image, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid image URL');
        }
    }

    /**
     * Log failed API responses
     */
    protected function logApiResponse(Response $response): void
    {
        if ($response->failed()) {
            Log::warning('Azure Multimodal API request failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'endpoint' => $this->config->endpoint,
            ]);
        }
    }

    /**
     * Format error message from API response
     */
    protected function getErrorMessage(Response $response): string
    {
        $body = $response->json();
        $error = $body['error'] ?? [];

        return sprintf(
            'Azure Multimodal API request failed (HTTP %d): [%s] %s',
            $response->status(),
            $error['code'] ?? 'unknown',
            $error['message'] ?? $response->body()
        );
    }
}
