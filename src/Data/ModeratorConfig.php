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
     * @param  string  $endpoint  Azure Content Safety API endpoint
     * @param  string  $apiKey  Azure Content Safety API key
     * @param  float  $lowRatingThreshold  Threshold for flagging low-rated content (0-5)
     * @param  int  $highSeverityThreshold  Threshold for flagging high-risk content (0-7)
     * @param  bool  $failOnApiError  Whether to fail validation when API is unavailable
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $apiKey,
        public readonly float $lowRatingThreshold = 4,
        public readonly int $highSeverityThreshold = 6,
        public readonly bool $failOnApiError = false,
    ) {}

    /**
     * Create a configuration instance from Laravel config values
     */
    public static function fromConfig(): self
    {
        return new self(
            endpoint: config('azure-moderator.endpoint'),
            apiKey: config('azure-moderator.api_key'),
            lowRatingThreshold: (float) config('azure-moderator.low_rating_threshold', 4),
            highSeverityThreshold: (int) config('azure-moderator.high_severity_threshold', 6),
            failOnApiError: config('azure-moderator.fail_on_api_error', false)
        );
    }
}
