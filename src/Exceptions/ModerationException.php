<?php

namespace Gowelle\AzureModerator\Exceptions;

/**
 * Exception thrown when content moderation fails
 *
 * This exception is thrown when the Azure Content Safety API request fails,
 * including connection issues, authentication errors, or invalid responses.
 * It includes additional context about the failed request such as the endpoint
 * and HTTP status code.
 *
 * Example:
 * ```php
 * throw new ModerationException(
 *     message: 'API request failed',
 *     endpoint: 'https://api.azure.com/content-safety',
 *     statusCode: 429
 * );
 * ```
 */
class ModerationException extends \Exception
{
    /**
     * Create a new ModerationException instance
     *
     * @param  string  $message  Error message describing what went wrong
     * @param  string|null  $endpoint  The API endpoint that was called
     * @param  int|null  $statusCode  HTTP status code from the response
     * @param  \Throwable|null  $previous  Previous exception if this was caused by another exception
     */
    public function __construct(
        string $message,
        public readonly ?string $endpoint = null,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
