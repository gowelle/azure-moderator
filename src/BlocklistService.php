<?php

namespace Gowelle\AzureModerator;

use Gowelle\AzureModerator\Data\ModeratorConfig;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Azure Content Safety Blocklist Service
 *
 * This service manages custom blocklists via Azure Content Safety API.
 * Blocklists allow you to define specific terms or phrases to be flagged
 * in text content, beyond the default AI classifiers.
 *
 * @see https://learn.microsoft.com/en-us/azure/ai-services/content-safety/how-to/use-blocklist
 */
class BlocklistService
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
     * Create a new blocklist
     *
     * @param  string  $name  Unique name for the blocklist
     * @param  string  $description  Description of the blocklist purpose
     * @return \Gowelle\AzureModerator\Data\Blocklist Created blocklist details
     *
     * @throws ModerationException
     */
    public function createBlocklist(string $name, string $description): \Gowelle\AzureModerator\Data\Blocklist
    {
        $endpoint = $this->buildEndpoint("/contentsafety/text/blocklists/{$name}");

        try {
            $response = $this->makeRequest('PATCH', $endpoint, [
                'description' => $description,
            ]);

            $data = $response->json();
            return new \Gowelle\AzureModerator\Data\Blocklist(
                name: $data['blocklistName'],
                description: $data['description'] ?? null
            );

        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Failed to create blocklist: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Get blocklist details
     *
     * @param  string  $name  Blocklist name
     * @return \Gowelle\AzureModerator\Data\Blocklist Blocklist details
     *
     * @throws ModerationException
     */
    public function getBlocklist(string $name): \Gowelle\AzureModerator\Data\Blocklist
    {
        $endpoint = $this->buildEndpoint("/contentsafety/text/blocklists/{$name}");

        try {
            $response = $this->makeRequest('GET', $endpoint);

            $data = $response->json();
            return new \Gowelle\AzureModerator\Data\Blocklist(
                name: $data['blocklistName'],
                description: $data['description'] ?? null
            );

        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Failed to get blocklist: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * List all blocklists
     *
     * @return array<int, \Gowelle\AzureModerator\Data\Blocklist> List of blocklists
     *
     * @throws ModerationException
     */
    public function listBlocklists(): array
    {
        $endpoint = $this->buildEndpoint('/contentsafety/text/blocklists');

        try {
            $response = $this->makeRequest('GET', $endpoint);
            $data = $response->json()['value'] ?? [];

            return array_map(fn ($item) => new \Gowelle\AzureModerator\Data\Blocklist(
                name: $item['blocklistName'],
                description: $item['description'] ?? null
            ), $data);

        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Failed to list blocklists: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Delete a blocklist
     *
     * @param  string  $name  Blocklist name to delete
     * @return bool True if deleted successfully
     *
     * @throws ModerationException
     */
    public function deleteBlocklist(string $name): bool
    {
        $endpoint = $this->buildEndpoint("/contentsafety/text/blocklists/{$name}");

        try {
            $response = $this->makeRequest('DELETE', $endpoint);

            return $response->successful();

        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Failed to delete blocklist: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Add items (terms) to a blocklist
     *
     * @param  string  $blocklistName  Name of the blocklist
     * @param  array<string>  $items  Array of terms to add
     * @return array<int, \Gowelle\AzureModerator\Data\BlocklistItem> Added items
     *
     * @throws ModerationException
     */
    public function addBlocklistItems(string $blocklistName, array $items): array
    {
        $endpoint = $this->buildEndpoint("/contentsafety/text/blocklists/{$blocklistName}:addOrUpdateBlocklistItems");

        $blocklistItems = array_map(fn ($text) => ['text' => $text], $items);

        try {
            $response = $this->makeRequest('POST', $endpoint, [
                'blocklistItems' => $blocklistItems,
            ]);

            $data = $response->json()['blocklistItems'] ?? [];

            return array_map(fn ($item) => new \Gowelle\AzureModerator\Data\BlocklistItem(
                id: $item['blocklistItemId'],
                text: $item['text']
            ), $data);

        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Failed to add blocklist items: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Remove an item from a blocklist
     *
     * @param  string  $blocklistName  Name of the blocklist
     * @param  string  $itemId  ID of the item to remove
     * @return bool True if removed successfully
     *
     * @throws ModerationException
     */
    public function removeBlocklistItem(string $blocklistName, string $itemId): bool
    {
        $endpoint = $this->buildEndpoint("/contentsafety/text/blocklists/{$blocklistName}:removeBlocklistItems");

        try {
            $response = $this->makeRequest('POST', $endpoint, [
                'blocklistItemIds' => [$itemId],
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Failed to remove blocklist item: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * List all items in a blocklist
     *
     * @param  string  $blocklistName  Name of the blocklist
     * @return array<int, \Gowelle\AzureModerator\Data\BlocklistItem> List of blocklist items
     *
     * @throws ModerationException
     */
    public function listBlocklistItems(string $blocklistName): array
    {
        $endpoint = $this->buildEndpoint("/contentsafety/text/blocklists/{$blocklistName}/blocklistItems");

        try {
            $response = $this->makeRequest('GET', $endpoint);
            $data = $response->json()['value'] ?? [];

            return array_map(fn ($item) => new \Gowelle\AzureModerator\Data\BlocklistItem(
                id: $item['blocklistItemId'],
                text: $item['text']
            ), $data);

        } catch (\Exception $e) {
            throw new ModerationException(
                message: "Failed to list blocklist items: {$e->getMessage()}",
                endpoint: $this->config->endpoint,
                previous: $e
            );
        }
    }

    /**
     * Build full endpoint URL
     *
     * @param  string  $path  API path
     */
    protected function buildEndpoint(string $path): string
    {
        return rtrim($this->config->endpoint, '/').$path.'?api-version='.self::API_VERSION;
    }

    /**
     * Make an HTTP request to Azure API
     *
     * @param  string  $method  HTTP method (GET, POST, PATCH, DELETE)
     * @param  string  $endpoint  Full endpoint URL
     * @param  array<string, mixed>  $data  Request payload
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        try {
            $request = Http::retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                return $exception instanceof RequestException &&
                       in_array($exception->response->status(), self::RETRY_STATUS_CODES);
            })->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->config->apiKey,
                'Content-Type' => 'application/json',
            ]);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($endpoint),
                'POST' => $request->post($endpoint, $data),
                'PATCH' => $request->patch($endpoint, $data),
                'DELETE' => $request->delete($endpoint),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

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
            Log::warning('Azure Blocklist API request failed', [
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
