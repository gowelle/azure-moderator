<?php

namespace Gowelle\AzureModerator\Commands;

use Gowelle\AzureModerator\Data\ModerationResult;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Illuminate\Console\Command;

/**
 * Artisan command for testing image moderation
 *
 * This command allows developers to test the image moderation functionality
 * directly from the command line, useful for debugging and verifying configuration.
 *
 * Usage:
 * php artisan azure-moderator:test-image https://example.com/image.jpg
 * php artisan azure-moderator:test-image https://example.com/image.jpg --categories=Sexual,Violence
 */
class TestImageModerationCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'azure-moderator:test-image
                            {image : The URL of the image to test}
                            {--categories= : Comma-separated list of categories to check (Hate,SelfHarm,Sexual,Violence)}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Test image moderation using Azure Content Safety API';

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $url = $this->argument('image');
        $categoriesOption = $this->option('categories');

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('Invalid URL provided.');

            return self::FAILURE;
        }

        $this->info('Testing image moderation...');
        $this->line("Image URL: {$url}");

        // Parse categories if provided
        $categories = null;
        if ($categoriesOption) {
            $categories = array_map('trim', explode(',', $categoriesOption));
            $this->line('Categories: '.implode(', ', $categories));
        } else {
            $this->line('Categories: All (Hate, SelfHarm, Sexual, Violence)');
        }

        $this->newLine();

        try {
            // Moderate the image
            $result = AzureModerator::moderateImage(
                image: $url,
                categories: $categories
            );

            // Display results
            $this->displayResults($result);

            return self::SUCCESS;

        } catch (ModerationException $e) {
            $this->error('Moderation failed!');
            $this->line("Error: {$e->getMessage()}");

            if ($e->endpoint) {
                $this->line("Endpoint: {$e->endpoint}");
            }

            if ($e->statusCode) {
                $this->line("Status Code: {$e->statusCode}");
            }

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('Unexpected error occurred!');
            $this->line("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display the moderation results
     *
     * @param  ModerationResult  $result  The moderation result
     */
    protected function displayResults(ModerationResult $result): void
    {
        $status = $result->isApproved() ? 'approved' : 'flagged';
        $reason = $result->reason;
        $scores = $result->categoriesAnalysis;

        // Display header
        $this->newLine();
        $this->line('Image Moderation Result');
        $this->line(str_repeat('=', 30));

        // Display status with color
        if ($status === 'approved') {
            $this->info('✓ Status: APPROVED');
        } else {
            $this->error('✗ Status: FLAGGED');
            if ($reason) {
                $this->line("Reason: {$reason}");
            }
        }

        // Display severity scores
        if (! empty($scores)) {
            $this->newLine();
            $this->line('Severity Scores:');

            $tableData = [];
            foreach ($scores as $score) {
                $category = $score->category;
                $severity = $score->severity;

                // Color code based on severity
                $severityDisplay = match (true) {
                    $severity >= 6 => "<fg=red>{$severity} (High)</>",
                    $severity >= 3 => "<fg=yellow>{$severity} (Medium)</>",
                    default => "<fg=green>{$severity} (Low)</>"
                };

                $tableData[] = [$category, $severityDisplay];
            }

            $this->table(['Category', 'Severity'], $tableData);
        }

        $this->newLine();
        $this->line('Configuration:');
        $this->line('High Severity Threshold: '.config('azure-moderator.high_severity_threshold', 3));
    }
}
