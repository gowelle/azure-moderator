<?php

use Gowelle\AzureModerator\BlocklistService;
use Gowelle\AzureModerator\Data\Blocklist;
use Gowelle\AzureModerator\Data\BlocklistItem;
use Gowelle\AzureModerator\Exceptions\ModerationException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'azure-moderator.endpoint' => 'https://test.cognitiveservices.azure.com',
        'azure-moderator.api_key' => 'test-api-key',
    ]);
});

test('can create a blocklist', function () {
    Http::fake([
        '*/contentsafety/text/blocklists/test-blocklist*' => Http::response([
            'blocklistName' => 'test-blocklist',
            'description' => 'Test blocklist description',
        ], 200),
    ]);

    $service = new BlocklistService;
    $result = $service->createBlocklist('test-blocklist', 'Test blocklist description');

    expect($result)->toBeInstanceOf(Blocklist::class)
        ->and($result->name)->toBe('test-blocklist')
        ->and($result->description)->toBe('Test blocklist description');
});

test('can get a blocklist', function () {
    Http::fake([
        '*/contentsafety/text/blocklists/test-blocklist*' => Http::response([
            'blocklistName' => 'test-blocklist',
            'description' => 'Test blocklist description',
        ], 200),
    ]);

    $service = new BlocklistService;
    $result = $service->getBlocklist('test-blocklist');

    expect($result)->toBeInstanceOf(Blocklist::class)
        ->and($result->name)->toBe('test-blocklist')
        ->and($result->description)->toBe('Test blocklist description');
});

test('can list blocklists', function () {
    Http::fake([
        '*/contentsafety/text/blocklists*' => Http::response([
            'value' => [
                ['blocklistName' => 'blocklist-1', 'description' => 'First blocklist'],
                ['blocklistName' => 'blocklist-2', 'description' => 'Second blocklist'],
            ],
        ], 200),
    ]);

    $service = new BlocklistService;
    $result = $service->listBlocklists();

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(Blocklist::class)
        ->and($result[0]->name)->toBe('blocklist-1');
});

test('can delete a blocklist', function () {
    Http::fake([
        '*/contentsafety/text/blocklists/test-blocklist*' => Http::response([], 204),
    ]);

    $service = new BlocklistService;
    $result = $service->deleteBlocklist('test-blocklist');

    expect($result)->toBeTrue();
});

test('can add items to a blocklist', function () {
    Http::fake([
        '*/contentsafety/text/blocklists/test-blocklist:addOrUpdateBlocklistItems*' => Http::response([
            'blocklistItems' => [
                ['blocklistItemId' => 'item-1', 'text' => 'badword'],
                ['blocklistItemId' => 'item-2', 'text' => 'anotherbadword'],
            ],
        ], 200),
    ]);

    $service = new BlocklistService;
    $result = $service->addBlocklistItems('test-blocklist', ['badword', 'anotherbadword']);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(BlocklistItem::class)
        ->and($result[0]->id)->toBe('item-1')
        ->and($result[0]->text)->toBe('badword');
});

test('can remove an item from a blocklist', function () {
    Http::fake([
        '*/contentsafety/text/blocklists/test-blocklist:removeBlocklistItems*' => Http::response([], 204),
    ]);

    $service = new BlocklistService;
    $result = $service->removeBlocklistItem('test-blocklist', 'item-1');

    expect($result)->toBeTrue();
});

test('can list blocklist items', function () {
    Http::fake([
        '*/contentsafety/text/blocklists/test-blocklist/blocklistItems*' => Http::response([
            'value' => [
                ['blocklistItemId' => 'item-1', 'text' => 'badword'],
                ['blocklistItemId' => 'item-2', 'text' => 'anotherbadword'],
            ],
        ], 200),
    ]);

    $service = new BlocklistService;
    $result = $service->listBlocklistItems('test-blocklist');

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(BlocklistItem::class)
        ->and($result[0]->text)->toBe('badword');
});

test('throws exception on API error when creating blocklist', function () {
    Http::fake([
        '*/contentsafety/text/blocklists/*' => Http::response([
            'error' => [
                'code' => 'InvalidRequest',
                'message' => 'Blocklist already exists',
            ],
        ], 409),
    ]);

    $service = new BlocklistService;
    $service->createBlocklist('existing-blocklist', 'Description');
})->throws(ModerationException::class);

test('throws exception on API error when getting blocklist', function () {
    Http::fake([
        '*/contentsafety/text/blocklists/*' => Http::response([
            'error' => [
                'code' => 'NotFound',
                'message' => 'Blocklist not found',
            ],
        ], 404),
    ]);

    $service = new BlocklistService;
    $service->getBlocklist('nonexistent-blocklist');
})->throws(ModerationException::class);

test('retries on rate limit errors', function () {
    Http::fake([
        '*/contentsafety/text/blocklists*' => Http::sequence()
            ->push(['error' => ['code' => 'RateLimitExceeded']], 429)
            ->push(['error' => ['code' => 'RateLimitExceeded']], 429)
            ->push(['value' => []], 200),
    ]);

    $service = new BlocklistService;
    $result = $service->listBlocklists();

    expect($result)->toBeArray();
    Http::assertSentCount(3);
});
