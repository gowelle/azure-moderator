<?php

namespace Gowelle\AzureModerator\Data;

/**
 * Configuration data object for Azure Content Safety moderation
 *
 * This class holds configuration values for the Azure Content Safety service,
 * including endpoint, API key, and threshold settings.
 */
class ModeratorConfig
{
    /**
     * Create a new ModeratorConfig instance
     *
     * @param string $endpoint Azure Content Safety API endpoint
     * @param string $apiKey Azure Content Safety API key
     * @param int $lowRatingThreshold Threshold for flagging low-rated content (0-5)
     * @param int $highSeverityThreshold Threshold for flagging high-risk content (0-7)
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $apiKey,
        public readonly int $lowRatingThreshold = 4,
        public readonly int $highSeverityThreshold = 6,
    ) {}

    /**
     * Create a configuration instance from Laravel config values
     *
     * @return self
     */
    public static function fromConfig(): self
    {
        return new self(
            endpoint: config('azure-moderator.endpoint'),
            apiKey: config('azure-moderator.api_key'),
            lowRatingThreshold: config('azure-moderator.low_rating_threshold', 4),
            highSeverityThreshold: config('azure-moderator.high_severity_threshold', 6)
        );
    }
}