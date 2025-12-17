<?php

namespace Tests\Performance;

use Gowelle\AzureModerator\Facades\AzureModerator;
use Tests\TestCase;

/**
 * Performance benchmarks for image moderation
 *
 * These tests measure performance metrics for image moderation operations.
 */
class ImageModerationBenchmark extends TestCase
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
    public function benchmark_url_based_image_moderation(): void
    {
        $iterations = 10;
        $times = [];
        $imageUrl = 'https://via.placeholder.com/150';

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            AzureModerator::moderateImage($imageUrl);

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        echo "\n";
        echo "URL-based Image Moderation Performance:\n";
        echo "  Iterations: {$iterations}\n";
        echo '  Average: '.number_format($avgTime, 2)." ms\n";
        echo '  Min: '.number_format($minTime, 2)." ms\n";
        echo '  Max: '.number_format($maxTime, 2)." ms\n";

        expect($avgTime)->toBeLessThan(3000);
    }

    /** @test */
    public function benchmark_base64_image_moderation(): void
    {
        $iterations = 10;
        $times = [];

        // Small test image (1x1 pixel PNG)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $base64Image = base64_encode($pngData);

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            AzureModerator::moderateImage($base64Image, encoding: 'base64');

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        $avgTime = array_sum($times) / count($times);

        echo "\n";
        echo "Base64 Image Moderation Performance:\n";
        echo "  Iterations: {$iterations}\n";
        echo '  Average: '.number_format($avgTime, 2)." ms\n";

        expect($avgTime)->toBeLessThan(3000);
    }

    /** @test */
    public function compare_url_vs_base64_performance(): void
    {
        $iterations = 5;

        // URL-based
        $urlTimes = [];
        $imageUrl = 'https://via.placeholder.com/150';

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            AzureModerator::moderateImage($imageUrl);
            $endTime = microtime(true);
            $urlTimes[] = ($endTime - $startTime) * 1000;
        }

        // Base64-based
        $base64Times = [];
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $base64Image = base64_encode($pngData);

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            AzureModerator::moderateImage($base64Image, encoding: 'base64');
            $endTime = microtime(true);
            $base64Times[] = ($endTime - $startTime) * 1000;
        }

        $urlAvg = array_sum($urlTimes) / count($urlTimes);
        $base64Avg = array_sum($base64Times) / count($base64Times);

        echo "\n";
        echo "URL vs Base64 Performance Comparison:\n";
        echo '  URL Average: '.number_format($urlAvg, 2)." ms\n";
        echo '  Base64 Average: '.number_format($base64Avg, 2)." ms\n";
        echo '  Difference: '.number_format(abs($urlAvg - $base64Avg), 2)." ms\n";

        // Both should be reasonable
        expect($urlAvg)->toBeLessThan(5000);
        expect($base64Avg)->toBeLessThan(5000);
    }

    /** @test */
    public function benchmark_base64_image_size_limits(): void
    {
        $sizes = [
            '1KB' => 1024,
            '10KB' => 10240,
            '100KB' => 102400,
            '500KB' => 512000,
            '1MB' => 1048576,
        ];

        $results = [];

        foreach ($sizes as $label => $bytes) {
            // Create base64 data of specified size
            $data = str_repeat('a', $bytes);

            $startTime = microtime(true);
            AzureModerator::moderateImage($data, encoding: 'base64');
            $endTime = microtime(true);

            $results[$label] = ($endTime - $startTime) * 1000;
        }

        echo "\n";
        echo "Base64 Image Size Performance:\n";
        foreach ($results as $size => $time) {
            echo "  {$size}: ".number_format($time, 2)." ms\n";
        }

        // All should complete
        foreach ($results as $time) {
            expect($time)->toBeLessThan(10000);
        }
    }

    /** @test */
    public function benchmark_concurrent_image_moderation_requests(): void
    {
        $concurrentRequests = 5;
        $imageUrl = 'https://via.placeholder.com/150';

        $startTime = microtime(true);

        $results = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $results[] = AzureModerator::moderateImage($imageUrl);
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $avgTimePerRequest = $totalTime / $concurrentRequests;

        echo "\n";
        echo "Concurrent Image Moderation Performance:\n";
        echo "  Concurrent Requests: {$concurrentRequests}\n";
        echo '  Total Time: '.number_format($totalTime, 2)." ms\n";
        echo '  Avg per Request: '.number_format($avgTimePerRequest, 2)." ms\n";

        expect($results)->toHaveCount($concurrentRequests);
    }

    /** @test */
    public function test_4mb_base64_limit_edge_case(): void
    {
        // Test with data just under 4MB limit
        $maxSize = 4194304; // 4MB in bytes
        $testSize = $maxSize - 1000; // Just under limit

        $data = str_repeat('a', $testSize);

        $startTime = microtime(true);
        $result = AzureModerator::moderateImage($data, encoding: 'base64');
        $endTime = microtime(true);

        $time = ($endTime - $startTime) * 1000;

        echo "\n";
        echo "4MB Limit Edge Case Performance:\n";
        echo '  Size: '.number_format($testSize / 1024 / 1024, 2)." MB\n";
        echo '  Time: '.number_format($time, 2)." ms\n";

        expect($result)->toBeInstanceOf(\Gowelle\AzureModerator\Data\ModerationResult::class);
    }
}
