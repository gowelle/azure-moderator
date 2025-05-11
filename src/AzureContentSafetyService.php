<?php

namespace Gowelle\AzureModerator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class AzureContentSafetyService implements \Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract
{
    protected string|null $endpoint;
    protected string|null $apiKey;

    public function __construct()
    {
        $this->endpoint = config('azure-moderator.endpoint');
        $this->apiKey = config('azure-moderator.api_key');
    }

    public function moderate(string $text, float $rating): array
    {
        try {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->endpoint . '/text/analytics:analyze', [
                'text' => $text,
                'categories' => ['Hate', 'SelfHarm', 'Sexual', 'Violence'],
            ]);

            $this->logApiResponse($response);

            if (! $response->successful()) {
                throw new \Exception($this->getErrorMessage($response));
            }

            $result = $response->json();
            $scores = $result['categoriesAnalysis'] ?? [];
            
            $hasHighRisk = collect($scores)->contains(function ($item) {
                return $item['severity'] >= 3; // 3 = high severity
            });

            if (!$hasHighRisk && $rating >= 4) {
                return ['status' => 'approved', 'reason' => null];
            }

            $reason = collect($scores)
                ->filter(fn ($item) => $item['severity'] >= 3)
                ->pluck('category')
                ->implode(', ');

            return [
                'status' => 'flagged',
                'reason' => $reason ?: 'low_rating',
            ];
        } catch (\Exception $e) {
            Log::error('Azure moderation failed', [
                'error' => $e->getMessage(),
                'endpoint' => $this->endpoint,
                'text_length' => strlen($text),
                'rating' => $rating
            ]);

            // fallback logic: approve if rating ≥ 4, else flag
            return $rating >= 4
                ? ['status' => 'approved', 'reason' => null]
                : ['status' => 'flagged', 'reason' => 'low_rating'];
        }
    }

    protected function logApiResponse(Response $response): void
    {
        if (! $response->successful()) {
            Log::warning('Azure API request failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'endpoint' => $this->endpoint
            ]);
        }
    }

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