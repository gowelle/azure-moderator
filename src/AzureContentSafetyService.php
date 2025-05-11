<?php

namespace Gowelle\AzureModerator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            ])->post($this->endpoint, [
                'text' => $text,
                'categories' => ['Hate', 'SelfHarm', 'Sexual', 'Violence'],
            ]);

            if (! $response->successful()) {
                throw new \Exception('Azure API request failed: ' . $response->body());
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
            Log::error('Azure moderation failed: ' . $e->getMessage());

            // fallback logic: approve if rating ≥ 4, else flag
            return $rating >= 4
                ? ['status' => 'approved', 'reason' => null]
                : ['status' => 'flagged', 'reason' => 'low_rating'];
        }
    }
}