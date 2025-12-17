<?php

namespace Gowelle\AzureModerator;

use Gowelle\AzureModerator\Data\ModeratorConfig;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Azure Content Safety Protected Material Service
 *
 * This service detects protected material (copyrighted content) in text
 * using Azure Content Safety API. Useful for detecting song lyrics, articles,
 * recipes, and other copyrighted text content.
 *
 * @see https://learn.microsoft.com/en-us/azure/ai-services/content-safety/concepts/protected-material
 */
class ProtectedMaterialService
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
     * Detect protected material in text
     *
     * Analyzes text content to detect known copyrighted material such as
     * song lyrics, articles, recipes, and other protected content.
     *
     * @param  string  $text  Text content to analyze
     * @return \Gowelle\AzureModerator\Data\ProtectedMaterialResult Detection result
     *
     * @throws ModerationException
     */
    public function detectProtectedMaterial(string $text): \Gowelle\AzureModerator\Data\ProtectedMaterialResult
    {
        if (empty($text)) {
            throw new \InvalidArgumentException('Text cannot be empty');
        }

        $endpoint = rtrim($this->config->endpoint, '/')
            .'/contentsafety/text:detectProtectedMaterial?api-version='.self::API_VERSION;

        try {
            $response = $this->makeRequest($endpoint, ['text' => $text]);

            $result = $response->json();

            return new \Gowelle\AzureModerator\Data\ProtectedMaterialResult(
                detected: $result['protectedMaterialAnalysis']['detected'] ?? false,
                details: $result['protectedMaterialAnalysis'] ?? [],
            );


        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Failed to detect protected material: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Make an HTTP request to Azure API
     *
     * @param  string  $endpoint  Full endpoint URL
     * @param  array<string, mixed>  $data  Request payload
     */
    protected function makeRequest(string $endpoint, array $data): Response
    {
        try {
            $response = Http::retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                return $exception instanceof RequestException &&
                       in_array($exception->response->status(), self::RETRY_STATUS_CODES);
            })->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->config->apiKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $data);

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
            Log::warning('Azure Protected Material API request failed', [
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
}
