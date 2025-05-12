<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Azure Content Safety API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for the Azure Content Safety
    | service integration. It includes the API endpoint, authentication key,
    | and various thresholds for content moderation.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API Endpoint
    |--------------------------------------------------------------------------
    |
    | The base URL for the Azure Content Safety API. This should be the full
    | endpoint URL provided in your Azure portal.
    |
    */
    'endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your Azure Content Safety API key. This is used to authenticate requests
    | to the API. Keep this value secure and never commit it to version control.
    |
    */
    'api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Low Rating Threshold
    |--------------------------------------------------------------------------
    |
    | Content with ratings below this threshold will be flagged for review.
    | The value should be between 0 and 5, where 5 is the highest rating.
    | Default: 2
    |
    */
    'low_rating_threshold' => env('AZURE_CONTENT_SAFETY_LOW_RATING_THRESHOLD', 2),

    /*
    |--------------------------------------------------------------------------
    | High Severity Threshold
    |--------------------------------------------------------------------------
    |
    | Content with a severity score at or above this threshold will be flagged.
    | Azure severity ranges from 0 to 7:
    | - 0-2: Low severity
    | - 3-5: Medium severity
    | - 6-7: High severity
    | Default: 3 (Medium severity)
    |
    */
    'high_severity_threshold' => env('AZURE_CONTENT_SAFETY_HIGH_SEVERITY_THRESHOLD', 3),
];
