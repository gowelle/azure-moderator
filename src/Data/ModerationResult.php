<?php

namespace Gowelle\AzureModerator\Data;

use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Enums\ModerationStatus;

/**
 * Data object for moderation results
 *
 * This class represents the outcome of a content moderation check,
 * including the status, reasons, scores, and blocklist matches.
 */
class ModerationResult
{
    /**
     * @param ModerationStatus $status Main moderation status
     * @param string|null $reason Reason for flagging (if any)
     * @param string|null $trackingId Azure tracking ID
     * @param CategoryAnalysis[] $categoriesAnalysis Detailed category scores
     * @param BlocklistMatch[]|null $blocklistMatches Blocklist matches (if any)
     */
    public function __construct(
        public readonly ModerationStatus $status,
        public readonly ?string $reason = null,
        public readonly ?string $trackingId = null,
        public readonly array $categoriesAnalysis = [],
        public readonly ?array $blocklistMatches = null,
    ) {}

    /**
     * Check if content is approved
     */
    public function isApproved(): bool
    {
        return $this->status === ModerationStatus::APPROVED;
    }

    /**
     * Check if content is flagged
     */
    public function isFlagged(): bool
    {
        return $this->status === ModerationStatus::FLAGGED;
    }

    /**
     * Get severity score for a specific category
     */
    public function getSeverity(ContentCategory $category): int
    {
        foreach ($this->categoriesAnalysis as $analysis) {
            if ($analysis->category === $category) {
                return $analysis->severity;
            }
        }
        return 0;
    }

    /**
     * Convert the result to an array
     *
     * @return array{status: string, reason: string|null, trackingId: string|null, categoriesAnalysis: array, blocklistMatches: array|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'reason' => $this->reason,
            'trackingId' => $this->trackingId,
            'categoriesAnalysis' => array_map(fn ($c) => [
                'category' => $c->category->value,
                'severity' => $c->severity,
            ], $this->categoriesAnalysis),
            'blocklistMatches' => $this->blocklistMatches ? array_map(fn ($m) => [
                'blocklistName' => $m->blocklistName,
                'matchId' => $m->matchId,
                'matchValue' => $m->matchValue,
            ], $this->blocklistMatches) : null,
        ];
    }
}
