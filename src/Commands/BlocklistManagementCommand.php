<?php

namespace Gowelle\AzureModerator\Commands;

use Gowelle\AzureModerator\BlocklistService;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Illuminate\Console\Command;

/**
 * Artisan command for managing custom blocklists
 *
 * This command provides a CLI interface for creating, managing, and deleting
 * custom blocklists in Azure Content Safety.
 *
 * Usage:
 * php artisan azure-moderator:blocklist create {name} {description}
 * php artisan azure-moderator:blocklist list
 * php artisan azure-moderator:blocklist show {name}
 * php artisan azure-moderator:blocklist delete {name}
 * php artisan azure-moderator:blocklist add-item {blocklist} {term}
 * php artisan azure-moderator:blocklist remove-item {blocklist} {itemId}
 * php artisan azure-moderator:blocklist list-items {blocklist}
 */
class BlocklistManagementCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'azure-moderator:blocklist
                            {action : Action to perform (create, list, show, delete, add-item, remove-item, list-items)}
                            {name? : Blocklist name (required for most actions)}
                            {value? : Additional value (description for create, term for add-item, itemId for remove-item)}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Manage Azure Content Safety custom blocklists';

    protected BlocklistService $blocklistService;

    /**
     * Create a new command instance
     */
    public function __construct(BlocklistService $blocklistService)
    {
        parent::__construct();
        $this->blocklistService = $blocklistService;
    }

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        try {
            return match ($action) {
                'create' => $this->createBlocklist(),
                'list' => $this->listBlocklists(),
                'show' => $this->showBlocklist(),
                'delete' => $this->deleteBlocklist(),
                'add-item' => $this->addBlocklistItem(),
                'remove-item' => $this->removeBlocklistItem(),
                'list-items' => $this->listBlocklistItems(),
                default => $this->invalidAction($action),
            };
        } catch (ModerationException $e) {
            $this->error('Operation failed!');
            $this->line("Error: {$e->getMessage()}");

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
     * Create a new blocklist
     */
    protected function createBlocklist(): int
    {
        $name = $this->argument('name');
        $description = $this->argument('value');

        if (! $name || ! $description) {
            $this->error('Both name and description are required for create action.');
            $this->line('Usage: php artisan azure-moderator:blocklist create {name} {description}');

            return self::FAILURE;
        }

        $this->info("Creating blocklist: {$name}");

        $result = $this->blocklistService->createBlocklist($name, $description);

        $this->info('✓ Blocklist created successfully!');
        $this->line("Name: {$result['blocklistName']}");
        $this->line("Description: {$result['description']}");

        return self::SUCCESS;
    }

    /**
     * List all blocklists
     */
    protected function listBlocklists(): int
    {
        $this->info('Fetching blocklists...');

        $result = $this->blocklistService->listBlocklists();
        $blocklists = $result['value'] ?? [];

        if (empty($blocklists)) {
            $this->warn('No blocklists found.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Available Blocklists');
        $this->line(str_repeat('=', 50));

        $tableData = [];
        foreach ($blocklists as $blocklist) {
            $tableData[] = [
                $blocklist['blocklistName'],
                /** @phpstan-ignore-next-line */
                $blocklist['description'] ?? 'N/A',
            ];
        }

        $this->table(['Name', 'Description'], $tableData);

        return self::SUCCESS;
    }

    /**
     * Show blocklist details
     */
    protected function showBlocklist(): int
    {
        $name = $this->argument('name');

        if (! $name) {
            $this->error('Blocklist name is required.');
            $this->line('Usage: php artisan azure-moderator:blocklist show {name}');

            return self::FAILURE;
        }

        $this->info("Fetching blocklist: {$name}");

        $blocklist = $this->blocklistService->getBlocklist($name);

        $this->newLine();
        $this->line('Blocklist Details');
        $this->line(str_repeat('=', 50));
        $this->line("Name: {$blocklist['blocklistName']}");
        /** @phpstan-ignore-next-line */
        $description = $blocklist['description'] ?? 'N/A';
        $this->line("Description: {$description}");

        return self::SUCCESS;
    }

    /**
     * Delete a blocklist
     */
    protected function deleteBlocklist(): int
    {
        $name = $this->argument('name');

        if (! $name) {
            $this->error('Blocklist name is required.');
            $this->line('Usage: php artisan azure-moderator:blocklist delete {name}');

            return self::FAILURE;
        }

        if (! $this->confirm("Are you sure you want to delete blocklist '{$name}'?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info("Deleting blocklist: {$name}");

        $this->blocklistService->deleteBlocklist($name);

        $this->info('✓ Blocklist deleted successfully!');

        return self::SUCCESS;
    }

    /**
     * Add an item to a blocklist
     */
    protected function addBlocklistItem(): int
    {
        $blocklistName = $this->argument('name');
        $term = $this->argument('value');

        if (! $blocklistName || ! $term) {
            $this->error('Both blocklist name and term are required.');
            $this->line('Usage: php artisan azure-moderator:blocklist add-item {blocklist} {term}');

            return self::FAILURE;
        }

        $this->info("Adding term to blocklist '{$blocklistName}': {$term}");

        $items = $this->blocklistService->addBlocklistItems($blocklistName, [$term]);

        if (! empty($items)) {
            $this->info('✓ Term added successfully!');
            $this->line("Item ID: {$items[0]['blocklistItemId']}");
            $this->line("Text: {$items[0]['text']}");
        } else {
            $this->warn('Term may have been added, but no confirmation received.');
        }

        return self::SUCCESS;
    }

    /**
     * Remove an item from a blocklist
     */
    protected function removeBlocklistItem(): int
    {
        $blocklistName = $this->argument('name');
        $itemId = $this->argument('value');

        if (! $blocklistName || ! $itemId) {
            $this->error('Both blocklist name and item ID are required.');
            $this->line('Usage: php artisan azure-moderator:blocklist remove-item {blocklist} {itemId}');

            return self::FAILURE;
        }

        $this->info("Removing item from blocklist '{$blocklistName}': {$itemId}");

        $this->blocklistService->removeBlocklistItem($blocklistName, $itemId);

        $this->info('✓ Item removed successfully!');

        return self::SUCCESS;
    }

    /**
     * List all items in a blocklist
     */
    protected function listBlocklistItems(): int
    {
        $blocklistName = $this->argument('name');

        if (! $blocklistName) {
            $this->error('Blocklist name is required.');
            $this->line('Usage: php artisan azure-moderator:blocklist list-items {blocklist}');

            return self::FAILURE;
        }

        $this->info("Fetching items for blocklist: {$blocklistName}");

        $result = $this->blocklistService->listBlocklistItems($blocklistName);
        $items = $result['value'] ?? [];

        if (empty($items)) {
            $this->warn('No items found in this blocklist.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("Items in blocklist '{$blocklistName}'");
        $this->line(str_repeat('=', 50));

        $tableData = [];
        foreach ($items as $item) {
            $tableData[] = [
                $item['blocklistItemId'],
                $item['text'],
            ];
        }

        $this->table(['Item ID', 'Text'], $tableData);

        return self::SUCCESS;
    }

    /**
     * Handle invalid action
     */
    protected function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->newLine();
        $this->line('Available actions:');
        $this->line('  create        - Create a new blocklist');
        $this->line('  list          - List all blocklists');
        $this->line('  show          - Show blocklist details');
        $this->line('  delete        - Delete a blocklist');
        $this->line('  add-item      - Add a term to a blocklist');
        $this->line('  remove-item   - Remove a term from a blocklist');
        $this->line('  list-items    - List all terms in a blocklist');

        return self::FAILURE;
    }
}
