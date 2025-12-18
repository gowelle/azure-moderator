<?php

namespace Gowelle\AzureModerator\Data;

use Gowelle\AzureModerator\Enums\ContentCategory;
use Gowelle\AzureModerator\Enums\ModerationStatus;

/**
 * Data object for multimodal analysis results (Preview API)
 *
 * This class represents the outcome of a combined text+image moderation check
 * using the Azure Content Safety Multimodal API.
 *
 * @see https://learn.microsoft.com/en-us/azure/ai-services/content-safety/quickstart-multimodal
 */
class MultimodalResult
{
    /**
     * @param ModerationStatus $status Main moderation status
     * @param string|null $reason Reason for flagging (if any)
     * @param CategoryAnalysis[] $categoriesAnalysis Detailed category scores
     */
    public function __construct(
        public readonly ModerationStatus $status,
        public readonly ?string $reason = null,
        public readonly array $categoriesAnalysis = [],
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
     * @return array{status: string, reason: string|null, categoriesAnalysis: array<array{category: string, severity: int}>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'reason' => $this->reason,
            'categoriesAnalysis' => array_map(fn ($c) => [
                'category' => $c->category->value,
                'severity' => $c->severity,
            ], $this->categoriesAnalysis),
        ];
    }
}
