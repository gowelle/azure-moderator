<?php

use Gowelle\AzureModerator\AzureContentSafetyService;
use Gowelle\AzureModerator\AzureContentSafetyServiceProvider;
use Gowelle\AzureModerator\Data\ModerationResult;
use Gowelle\AzureModerator\Enums\ModerationStatus;
use Gowelle\AzureModerator\Events\ContentModerated;
use Gowelle\AzureModerator\Jobs\ModerateContentJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'azure-moderator.endpoint' => 'https://test.cognitiveservices.azure.com',
        'azure-moderator.api_key' => 'test-api-key',
        'azure-moderator.high_severity_threshold' => 3,
        'azure-moderator.low_rating_threshold' => 2.5,
    ]);
});

test('can moderate batch of text items', function () {
    Http::fake([
        '*/contentsafety/text:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
                ['category' => 'SelfHarm', 'severity' => 0],
                ['category' => 'Sexual', 'severity' => 0],
                ['category' => 'Violence', 'severity' => 0],
            ],
        ], 200),
    ]);

    $service = new AzureContentSafetyService;
    $items = [
        ['type' => 'text', 'content' => 'First comment', 'rating' => 4.5],
        ['type' => 'text', 'content' => 'Second comment', 'rating' => 3.0],
        ['type' => 'text', 'content' => 'Third comment', 'rating' => 5.0],
    ];

    $results = $service->moderateBatch($items);

    expect($results)->toHaveCount(3)
        ->and($results[0])->toBeInstanceOf(ModerationResult::class)
        ->and($results[0]->isApproved())->toBeTrue();
});

test('can moderate batch of mixed text and image items', function () {
    Http::fake([
        '*/contentsafety/text:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
            ],
        ], 200),
        '*/contentsafety/image:analyze*' => Http::response([
            'categoriesAnalysis' => [
                ['category' => 'Hate', 'severity' => 0],
            ],
        ], 200),
    ]);

    $service = new AzureContentSafetyService;
    $items = [
        ['type' => 'text', 'content' => 'A comment', 'rating' => 4.5],
        ['type' => 'image', 'content' => 'https://example.com/image.jpg'],
    ];

    $results = $service->moderateBatch($items);

    expect($results)->toHaveCount(2)
        ->and($results[0])->toBeInstanceOf(ModerationResult::class)
        ->and($results[1])->toBeInstanceOf(ModerationResult::class);
});

test('batch moderation handles individual item failures with graceful degradation', function () {
    Http::fake([
        '*/contentsafety/text:analyze*' => Http::sequence()
            ->push(['categoriesAnalysis' => [['category' => 'Hate', 'severity' => 0]]], 200)
            ->push(['error' => ['code' => 'Unauthorized', 'message' => 'Invalid API key']], 401)
            ->push(['categoriesAnalysis' => [['category' => 'Hate', 'severity' => 0]]], 200),
    ]);

    $service = new AzureContentSafetyService;
    $items = [
        ['type' => 'text', 'content' => 'Good comment', 'rating' => 4.5],
        ['type' => 'text', 'content' => 'Low rated comment', 'rating' => 2.0], // Low rating, will be flagged on API error
        ['type' => 'text', 'content' => 'Another good comment', 'rating' => 4.5],
    ];

    $results = $service->moderateBatch($items);

    expect($results)->toHaveCount(3)
        ->and($results[0]->status)->toBe(ModerationStatus::APPROVED)
        // Second item: API fails, but graceful degradation kicks in
        // With rating 2.0 (below threshold 2.5), it should be flagged (or approved depending on logic, but DTO returns approved/flagged)
        // Check AzureContentSafetyService:103 catch block
        ->and($results[1]->status)->toBe(ModerationStatus::FLAGGED) // Logic for low rating
        ->and($results[1]->reason)->toBe('low_rating')
        ->and($results[2]->status)->toBe(ModerationStatus::APPROVED);
});

test('can moderate text with image context', function () {
    Http::fake([
        '*/contentsafety/text:analyze*' => Http::response([
            'categoriesAnalysis' => [['category' => 'Hate', 'severity' => 0]],
        ], 200),
        '*/contentsafety/image:analyze*' => Http::response([
            'categoriesAnalysis' => [['category' => 'Violence', 'severity' => 0]],
        ], 200),
    ]);

    $service = new AzureContentSafetyService;
    $result = $service->moderateWithContext(
        text: 'Check out this photo',
        rating: 4.5,
        imageUrl: 'https://example.com/photo.jpg'
    );

    expect($result)->toHaveKey('text')
        ->and($result)->toHaveKey('image')
        ->and($result)->toHaveKey('combined')
        ->and($result['combined']->isApproved())->toBeTrue();
});

test('moderateWithContext flags if either text or image is flagged', function () {
    Http::fake([
        '*/contentsafety/text:analyze*' => Http::response([
            'categoriesAnalysis' => [['category' => 'Hate', 'severity' => 6]],
        ], 200),
        '*/contentsafety/image:analyze*' => Http::response([
            'categoriesAnalysis' => [['category' => 'Violence', 'severity' => 0]],
        ], 200),
    ]);

    $service = new AzureContentSafetyService;
    $result = $service->moderateWithContext(
        text: 'Hateful text',
        rating: 4.5,
        imageUrl: 'https://example.com/safe-image.jpg'
    );

    expect($result['text']->isFlagged())->toBeTrue()
        ->and($result['image']->isApproved())->toBeTrue()
        ->and($result['combined']->isFlagged())->toBeTrue()
        ->and($result['combined']->reason)->toContain('Hate');
});

test('moderateWithContext works without image', function () {
    Http::fake([
        '*/contentsafety/text:analyze*' => Http::response([
            'categoriesAnalysis' => [['category' => 'Hate', 'severity' => 0]],
        ], 200),
    ]);

    $service = new AzureContentSafetyService;
    $result = $service->moderateWithContext(
        text: 'Just text, no image',
        rating: 4.5
    );

    expect($result['text']->isApproved())->toBeTrue()
        ->and($result['image'])->toBeNull()
        ->and($result['combined']->isApproved())->toBeTrue();
});

test('ModerateContentJob dispatches event on completion', function () {
    $this->app->register(AzureContentSafetyServiceProvider::class);

    Event::fake();
    Http::fake([
        '*/contentsafety/text:analyze*' => Http::response([
            'categoriesAnalysis' => [['category' => 'Hate', 'severity' => 0]],
        ], 200),
    ]);

    $job = new ModerateContentJob(
        contentType: 'text',
        content: 'Test content',
        rating: 4.5,
        metadata: ['user_id' => 123]
    );

    $job->handle();

    Event::assertDispatched(ContentModerated::class, function ($event) {
        return $event->contentType === 'text'
            && $event->content === 'Test content'
            && $event->metadata['user_id'] === 123
            && $event->isApproved();
    });
});

test('ModerateContentJob handles image moderation', function () {
    $this->app->register(AzureContentSafetyServiceProvider::class);

    Event::fake();
    Http::fake([
        '*/contentsafety/image:analyze*' => Http::response([
            'categoriesAnalysis' => [['category' => 'Violence', 'severity' => 6]],
        ], 200),
    ]);

    $job = new ModerateContentJob(
        contentType: 'image',
        content: 'https://example.com/image.jpg'
    );

    $job->handle();

    Event::assertDispatched(ContentModerated::class, function ($event) {
        return $event->contentType === 'image'
            && $event->isFlagged()
            && $event->getReason() === 'Violence';
    });
});

test('ContentModerated event has helper methods', function () {
    $resultApproved = new ModerationResult(ModerationStatus::APPROVED); 
    $approvedEvent = new ContentModerated(
        result: $resultApproved,
        contentType: 'text',
        content: 'Safe content'
    );

    expect($approvedEvent->isApproved())->toBeTrue()
        ->and($approvedEvent->isFlagged())->toBeFalse()
        ->and($approvedEvent->getReason())->toBeNull();

    $resultFlagged = new ModerationResult(ModerationStatus::FLAGGED, 'Violence');
    $flaggedEvent = new ContentModerated(
        result: $resultFlagged,
        contentType: 'image',
        content: 'Unsafe image'
    );

    expect($flaggedEvent->isApproved())->toBeFalse()
        ->and($flaggedEvent->isFlagged())->toBeTrue()
        ->and($flaggedEvent->getReason())->toBe('Violence');
});

test('ModerateContentJob can be queued', function () {
    Queue::fake();

    dispatch(new ModerateContentJob(
        contentType: 'text',
        content: 'Test',
        rating: 4.5
    ));

    Queue::assertPushed(ModerateContentJob::class);
});
