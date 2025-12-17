<?php

use Gowelle\AzureModerator\BlocklistService;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Gowelle\AzureModerator\Facades\AzureModerator;

/**
 * Integration tests for Blocklist functionality
 *
 * These tests run against the real Azure Content Safety API
 * and require valid credentials in .env.integration
 */
beforeEach(function () {
    if (! env('AZURE_CONTENT_SAFETY_ENDPOINT') || ! env('AZURE_CONTENT_SAFETY_API_KEY')) {
        $this->markTestSkipped('Azure credentials not configured');
    }

    config([
        'azure-moderator.endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
        'azure-moderator.api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
    ]);
});

test('can create and delete a blocklist', function () {
    $service = new BlocklistService;
    $blocklistName = 'test-blocklist-'.time();

    try {
        // Create blocklist
        $result = $service->createBlocklist($blocklistName, 'Test blocklist for integration testing');

        expect($result)->toBeInstanceOf(\Gowelle\AzureModerator\Data\Blocklist::class)
            ->and($result->name)->toBe($blocklistName)
            ->and($result->description)->toBe('Test blocklist for integration testing');

        // Verify it exists
        $blocklist = $service->getBlocklist($blocklistName);
        expect($blocklist->name)->toBe($blocklistName);

    } finally {
        // Clean up - delete the blocklist
        try {
            $service->deleteBlocklist($blocklistName);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
})->group('integration', 'blocklist');

test('can list blocklists', function () {
    $service = new BlocklistService;
    $blocklistName = 'test-list-blocklist-' . time();

    try {
        // Ensure at least one blocklist exists
        $service->createBlocklist($blocklistName, 'Description');

        $result = $service->listBlocklists();

        expect($result)->toBeArray()
            ->and($result)->not->toBeEmpty();
            
        // Check finding our created blocklist
        $found = false;
        foreach ($result as $item) {
            expect($item)->toBeInstanceOf(\Gowelle\AzureModerator\Data\Blocklist::class);
            if ($item->name === $blocklistName) {
                $found = true;
            }
        }
        
        expect($found)->toBeTrue();
        
    } finally {
        // Cleanup
        try {
            $service->deleteBlocklist($blocklistName);
        } catch (\Exception $e) {}
    }
})->group('integration', 'blocklist');

test('can add and remove items from blocklist', function () {
    $service = new BlocklistService;
    $blocklistName = 'test-blocklist-items-'.time();

    try {
        // Create blocklist
        $service->createBlocklist($blocklistName, 'Test blocklist for item management');

        // Add items
        $items = $service->addBlocklistItems($blocklistName, ['badword', 'anotherbadword']);

        expect($items)->toHaveCount(2)
            ->and($items[0])->toHaveKey('id')
            ->and($items[0])->toHaveKey('text');

        $itemId = $items[0]->id;

        // List items
        $listResult = $service->listBlocklistItems($blocklistName);
        expect($listResult)->toHaveCount(2);

        // Remove an item
        $removed = $service->removeBlocklistItem($blocklistName, $itemId);
        expect($removed)->toBeTrue();

        // Verify item was removed
        $listResult = $service->listBlocklistItems($blocklistName);
        expect($listResult)->toHaveCount(1);

    } finally {
        // Clean up
        try {
            $service->deleteBlocklist($blocklistName);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
})->group('integration', 'blocklist');

test('can moderate text with blocklist', function () {
    $service = new BlocklistService;
    $blocklistName = 'test-moderation-blocklist-'.time();

    try {
        // Create blocklist and add a term
        $service->createBlocklist($blocklistName, 'Test blocklist for moderation');
        $service->addBlocklistItems($blocklistName, ['testbadword']);

        // Wait a moment for Azure to index the blocklist
        sleep(2);

        // Test moderation with blocklist
        $result = AzureModerator::moderate(
            text: 'This text contains testbadword which should be flagged',
            rating: 4.5,
            blocklistNames: [$blocklistName]
        );

        expect($result)->toBeInstanceOf(\Gowelle\AzureModerator\Data\ModerationResult::class)
            ->and($result->blocklistMatches)->not->toBeEmpty();

        // The text should be flagged due to blocklist match
        if (! empty($result->blocklistMatches)) {
            expect($result->isFlagged())->toBeTrue()
                ->and($result->reason)->toContain('blocklist_match');
        }

        // Test with clean text
        $cleanResult = AzureModerator::moderate(
            text: 'This is clean text without any problematic words',
            rating: 4.5,
            blocklistNames: [$blocklistName]
        );

        expect($cleanResult->isApproved())->toBeTrue();

    } finally {
        // Clean up
        try {
            $service->deleteBlocklist($blocklistName);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
})->group('integration', 'blocklist')->skip('Requires time for Azure indexing');

test('throws exception when blocklist not found', function () {
    $service = new BlocklistService;

    $service->getBlocklist('nonexistent-blocklist-'.time());
})->group('integration', 'blocklist')->throws(ModerationException::class);

test('can delete nonexistent blocklist (idempotent)', function () {
    $service = new BlocklistService;

    // Deleting a non-existent blocklist should succeed (return true) as per Azure API behavior
    $result = $service->deleteBlocklist('nonexistent-blocklist-'.time());
    
    expect($result)->toBeTrue();
})->group('integration', 'blocklist');
