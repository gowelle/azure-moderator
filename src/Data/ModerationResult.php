<?php

namespace Gowelle\AzureModerator\Data;

use Gowelle\AzureModerator\Enums\ModerationStatus;

/**
 * Data object for moderation results
 *
 * This class represents the outcome of a content moderation check,
 * including the status and any reasons for flagging.
 */
class ModerationResult
{
    /**
     * Create a new moderation result
     *
     * @param ModerationStatus $status The moderation status
     * @param string|null $reason Optional reason for flagging
     */
    public function __construct(
        public readonly ModerationStatus $status,
        public readonly ?string $reason = null,
    ) {}

    /**
     * Convert the result to an array
     *
     * @return array{status: string, reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'reason' => $this->reason,
        ];
    }
}