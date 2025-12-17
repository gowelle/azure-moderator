<?php

namespace Tests\Performance;

use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Tests\TestCase;

/**
 * Performance benchmarks for text moderation
 *
 * These tests measure performance metrics for text moderation operations.
 * Results are logged for analysis and optimization.
 */
class TextModerationBenchmark extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if Azure credentials are not configured
        if (! env('AZURE_CONTENT_SAFETY_ENDPOINT') || ! env('AZURE_CONTENT_SAFETY_API_KEY')) {
            $this->markTestSkipped('Azure Content Safety credentials not configured');
        }

        config([
            'azure-moderator.endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
            'azure-moderator.api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
        ]);
    }

    /** @test */
    public function benchmark_single_text_moderation_request(): void
    {
        $iterations = 10;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            AzureModerator::moderate("Benchmark test message {$i}", 4.0);

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        // Log results
        echo "\n";
        echo "Text Moderation Performance:\n";
        echo "  Iterations: {$iterations}\n";
        echo '  Average: '.number_format($avgTime, 2)." ms\n";
        echo '  Min: '.number_format($minTime, 2)." ms\n";
        echo '  Max: '.number_format($maxTime, 2)." ms\n";

        // Assert reasonable performance (< 3000ms average)
        expect($avgTime)->toBeLessThan(3000);
    }

    /** @test */
    public function benchmark_text_moderation_with_all_categories(): void
    {
        $iterations = 5;
        $times = [];

        $categories = [
            ContentCategory::HATE->value,
            ContentCategory::SELF_HARM->value,
            ContentCategory::SEXUAL->value,
            ContentCategory::VIOLENCE->value,
        ];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            AzureModerator::moderate("Benchmark test with all categories {$i}", 4.0, $categories);

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        $avgTime = array_sum($times) / count($times);

        echo "\n";
        echo "Text Moderation (All Categories) Performance:\n";
        echo "  Iterations: {$iterations}\n";
        echo '  Average: '.number_format($avgTime, 2)." ms\n";

        expect($avgTime)->toBeLessThan(3000);
    }

    /** @test */
    public function benchmark_concurrent_text_moderation_requests(): void
    {
        $concurrentRequests = 5;

        $startTime = microtime(true);

        $results = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $results[] = AzureModerator::moderate("Concurrent request {$i}", 4.0);
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $avgTimePerRequest = $totalTime / $concurrentRequests;

        echo "\n";
        echo "Concurrent Text Moderation Performance:\n";
        echo "  Concurrent Requests: {$concurrentRequests}\n";
        echo '  Total Time: '.number_format($totalTime, 2)." ms\n";
        echo '  Avg per Request: '.number_format($avgTimePerRequest, 2)." ms\n";

        // Verify all requests completed
        expect($results)->toHaveCount($concurrentRequests);
    }

    /** @test */
    public function benchmark_text_moderation_with_varying_content_length(): void
    {
        $contentLengths = [10, 50, 100, 500, 1000];
        $results = [];

        foreach ($contentLengths as $length) {
            $content = str_repeat('word ', $length / 5);

            $startTime = microtime(true);
            AzureModerator::moderate($content, 4.0);
            $endTime = microtime(true);

            $results[$length] = ($endTime - $startTime) * 1000;
        }

        echo "\n";
        echo "Text Moderation Performance by Content Length:\n";
        foreach ($results as $length => $time) {
            echo "  {$length} chars: ".number_format($time, 2)." ms\n";
        }

        // All should complete in reasonable time
        foreach ($results as $time) {
            expect($time)->toBeLessThan(5000);
        }
    }

    /** @test */
    public function measure_retry_logic_overhead(): void
    {
        // Measure time with valid credentials (no retries)
        $iterations = 5;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            AzureModerator::moderate("Retry overhead test {$i}", 4.0);
            $endTime = microtime(true);

            $times[] = ($endTime - $startTime) * 1000;
        }

        $avgTime = array_sum($times) / count($times);

        echo "\n";
        echo "Retry Logic Overhead Measurement:\n";
        echo '  Average time (no retries): '.number_format($avgTime, 2)." ms\n";

        // Without retries, should be relatively fast
        expect($avgTime)->toBeLessThan(3000);
    }
}
