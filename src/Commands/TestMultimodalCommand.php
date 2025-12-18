<?php

namespace Gowelle\AzureModerator\Commands;

use Gowelle\AzureModerator\Data\MultimodalResult;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\MultimodalService;
use Illuminate\Console\Command;

/**
 * Artisan command for testing multimodal content analysis (Preview API)
 *
 * This command allows developers to test combined image + text moderation
 * directly from the command line.
 *
 * Usage:
 * php artisan azure-moderator:test-multimodal https://example.com/image.jpg
 * php artisan azure-moderator:test-multimodal https://example.com/image.jpg --text="Caption text"
 * php artisan azure-moderator:test-multimodal path/to/image.jpg --local
 */
class TestMultimodalCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'azure-moderator:test-multimodal
                            {image : The URL or path of the image to test}
                            {--text= : Optional text to analyze with the image}
                            {--local : Treat image as local file path instead of URL}
                            {--categories= : Comma-separated list of categories (Hate,SelfHarm,Sexual,Violence)}
                            {--no-ocr : Disable OCR text extraction from image}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Test multimodal content analysis using Azure Content Safety API (Preview)';

    /**
     * Execute the console command
     */
    public function handle(MultimodalService $service): int
    {
        $image = $this->argument('image');
        $text = $this->option('text');
        $isLocal = $this->option('local');
        $categoriesOption = $this->option('categories');
        $enableOcr = ! $this->option('no-ocr');

        $this->warn('⚠️  Note: This uses the Azure Content Safety Preview API (2024-09-15-preview)');
        $this->newLine();

        $this->info('Testing multimodal content analysis...');
        $this->line("Image: {$image}");

        if ($text) {
            $this->line("Text: {$text}");
        }

        // Parse categories if provided
        $categories = null;
        if ($categoriesOption) {
            $categories = array_map('trim', explode(',', $categoriesOption));
            $this->line('Categories: '.implode(', ', $categories));
        }

        $this->line('OCR Enabled: '.($enableOcr ? 'Yes' : 'No'));
        $this->newLine();

        try {
            // Prepare image data
            if ($isLocal) {
                if (! file_exists($image)) {
                    $this->error("File not found: {$image}");

                    return self::FAILURE;
                }
                $contents = file_get_contents($image);
                if ($contents === false) {
                    $this->error("Unable to read file: {$image}");

                    return self::FAILURE;
                }
                $imageData = base64_encode($contents);
                $encoding = 'base64';
            } else {
                // Validate URL
                if (! filter_var($image, FILTER_VALIDATE_URL)) {
                    $this->error('Invalid URL provided. Use --local for local files.');

                    return self::FAILURE;
                }
                $imageData = $image;
                $encoding = 'url';
            }

            // Analyze content
            $result = $service->analyze(
                image: $imageData,
                text: $text,
                encoding: $encoding,
                categories: $categories,
                enableOcr: $enableOcr
            );

            // Display results
            $this->displayResults($result);

            return self::SUCCESS;

        } catch (ModerationException $e) {
            $this->error('Multimodal analysis failed!');
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
     * Display the analysis results
     */
    protected function displayResults(MultimodalResult $result): void
    {
        $this->newLine();
        $this->line('Multimodal Analysis Result');
        $this->line(str_repeat('=', 30));

        // Display status with color
        if ($result->isApproved()) {
            $this->info('✓ Status: APPROVED');
        } else {
            $this->error('✗ Status: FLAGGED');
            if ($result->reason) {
                $this->line("Reason: {$result->reason}");
            }
        }

        // Display severity scores
        $scores = $result->categoriesAnalysis;
        if (! empty($scores)) {
            $this->newLine();
            $this->line('Severity Scores:');

            $tableData = [];
            foreach ($scores as $score) {
                $severity = $score->severity;
                $severityDisplay = match (true) {
                    $severity >= 6 => "<fg=red>{$severity} (High)</>",
                    $severity >= 3 => "<fg=yellow>{$severity} (Medium)</>",
                    default => "<fg=green>{$severity} (Low)</>"
                };

                $tableData[] = [$score->category->value, $severityDisplay];
            }

            $this->table(['Category', 'Severity'], $tableData);
        }

        $this->newLine();
        $this->line('Configuration:');
        $this->line('High Severity Threshold: '.config('azure-moderator.high_severity_threshold', 3));
    }
}
