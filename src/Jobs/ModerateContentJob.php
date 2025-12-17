<?php

namespace Gowelle\AzureModerator\Jobs;

use Gowelle\AzureModerator\Events\ContentModerated;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for asynchronous content moderation
 *
 * This job allows content moderation to be processed in the background,
 * preventing blocking of user requests while waiting for Azure API responses.
 *
 * Usage:
 * ```php
 * dispatch(new ModerateContentJob('text', 'Some content', 4.5));
 * ```
 */
class ModerateContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The content type (text or image)
     */
    public string $contentType;

    /**
     * The content to moderate
     */
    public string $content;

    /**
     * The rating (for text moderation)
     */
    public ?float $rating;

    /**
     * Optional categories to check
     *
     * @var array<string>|null
     */
    public ?array $categories;

    /**
     * Optional blocklist names (for text moderation)
     *
     * @var array<string>|null
     */
    public ?array $blocklistNames;

    /**
     * Image encoding type (for image moderation)
     */
    public string $encoding;

    /**
     * Additional metadata
     *
     * @var array<string, mixed>
     */
    public array $metadata;

    /**
     * The number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job
     */
    public int $backoff = 60;

    /**
     * Create a new job instance
     *
     * @param  array<string>|null  $categories
     * @param  array<string>|null  $blocklistNames
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        string $contentType,
        string $content,
        ?float $rating = null,
        ?array $categories = null,
        ?array $blocklistNames = null,
        string $encoding = 'url',
        array $metadata = []
    ) {
        $this->contentType = $contentType;
        $this->content = $content;
        $this->rating = $rating;
        $this->categories = $categories;
        $this->blocklistNames = $blocklistNames;
        $this->encoding = $encoding;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            $result = match ($this->contentType) {
                'text' => AzureModerator::moderate(
                    text: $this->content,
                    rating: $this->rating ?? 5.0,
                    categories: $this->categories,
                    blocklistNames: $this->blocklistNames
                ),
                'image' => AzureModerator::moderateImage(
                    image: $this->content,
                    categories: $this->categories,
                    encoding: $this->encoding
                ),
                default => throw new \InvalidArgumentException("Invalid content type: {$this->contentType}"),
            };

            // Dispatch event
            event(new ContentModerated(
                result: $result,
                contentType: $this->contentType,
                content: $this->content,
                metadata: $this->metadata
            ));

        } catch (\Exception $e) {
            Log::error('Moderation job failed', [
                'content_type' => $this->contentType,
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
            ]);

            throw $e;
        }
    }
}
