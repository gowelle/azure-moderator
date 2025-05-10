<?php

namespace Gowelle\AzureModerator;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AzureContentSafetyService implements \Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract
{
    protected $client;

    public function __construct(protected $endpoint, protected $apiKey)
    {
        $this->client = new Client([
            'base_uri' => $this->endpoint,
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function moderate(string $text, float $rating): array
    {
        try {
            $response = $this->client->post('/contentsafety/text:analyze', [
                'json' => [
                    'text' => $text,
                    'categories' => ['Hate', 'SelfHarm', 'Sexual', 'Violence'],
                ],
            ]);

            $result = json_decode($response->getBody(), true);

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