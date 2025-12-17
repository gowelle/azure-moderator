<?php

namespace Gowelle\AzureModerator\Commands;

use Gowelle\AzureModerator\Data\ProtectedMaterialResult;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\ProtectedMaterialService;
use Illuminate\Console\Command;

/**
 * Artisan command for testing protected material detection
 *
 * This command allows developers to test the protected material detection
 * functionality directly from the command line.
 *
 * Usage:
 * php artisan azure-moderator:test-protected "some text to test"
 */
class TestProtectedMaterialCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'azure-moderator:test-protected
                            {text : The text content to test for protected material}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Test protected material detection using Azure Content Safety API';

    protected ProtectedMaterialService $service;

    /**
     * Create a new command instance
     */
    public function __construct(ProtectedMaterialService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $text = $this->argument('text');

        $this->info('Testing protected material detection...');
        $this->line("Text: {$text}");
        $this->newLine();

        try {
            $result = $this->service->detectProtectedMaterial($text);

            $this->displayResults($result);

            return self::SUCCESS;

        } catch (ModerationException $e) {
            $this->error('Detection failed!');
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
     * Display the detection results
     *
     * @param  ProtectedMaterialResult  $result  The detection result
     */
    protected function displayResults(ProtectedMaterialResult $result): void
    {
        $this->newLine();
        $this->line('Protected Material Detection Result');
        $this->line(str_repeat('=', 40));

        if ($result->detected) {
            $this->error('✗ PROTECTED MATERIAL DETECTED');
            $this->line('The text contains copyrighted or protected content.');
        } else {
            $this->info('✓ NO PROTECTED MATERIAL DETECTED');
            $this->line('The text appears to be original content.');
        }

        $this->newLine();
        $this->line('Analysis Details:');
        $this->line(json_encode($result->details, JSON_PRETTY_PRINT));
    }
}
