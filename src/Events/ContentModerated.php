<?php

namespace Gowelle\AzureModerator\Events;

use Gowelle\AzureModerator\Data\ModerationResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when content moderation is completed
 *
 * This event is fired after content (text or image) has been moderated
 * by Azure Content Safety API. Listeners can use this to implement
 * custom workflows like notifications, logging, or content updates.
 *
 * Usage:
 * ```php
 * Event::listen(ContentModerated::class, function ($event) {
 *     if ($event->isFlagged()) {
 *         // Handle flagged content
 *     }
 * });
 * ```
 */
class ContentModerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The moderation result
     */
    public ModerationResult $result;

    /**
     * The content type (text or image)
     */
    public string $contentType;

    /**
     * The moderated content
     */
    public string $content;

    /**
     * Additional metadata
     *
     * @var array<string, mixed>
     */
    public array $metadata;

    /**
     * Create a new event instance
     *
     * @param  ModerationResult  $result
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        ModerationResult $result,
        string $contentType,
        string $content,
        array $metadata = []
    ) {
        $this->result = $result;
        $this->contentType = $contentType;
        $this->content = $content;
        $this->metadata = $metadata;
    }

    /**
     * Check if content was flagged
     */
    public function isFlagged(): bool
    {
        return $this->result->isFlagged();
    }

    /**
     * Check if content was approved
     */
    public function isApproved(): bool
    {
        return $this->result->isApproved();
    }

    /**
     * Get the flagging reason
     */
    public function getReason(): ?string
    {
        return $this->result->reason;
    }
}
