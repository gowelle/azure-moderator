<?php

namespace Gowelle\AzureModerator\Enums;

/**
 * Enumeration of possible moderation status values
 *
 * This enum represents the possible outcomes of content moderation,
 * either approved or flagged for review.
 */
enum ModerationStatus: string
{
    /** Content passed moderation checks */
    case APPROVED = 'approved';

    /** Content needs review or was flagged */
    case FLAGGED = 'flagged';

    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::APPROVED => 'Approved',
            self::FLAGGED => 'Flagged',
        };
    }
}
