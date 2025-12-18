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

    /*
    |--------------------------------------------------------------------------
    | Fail on API Error
    |--------------------------------------------------------------------------
    |
    | Determines whether validation should fail when the Azure API is unavailable
    | or returns an error. When set to false (default), validation will pass
    | gracefully to prevent blocking users during API outages. When true,
    | validation will fail if the API cannot be reached.
    |
    | This setting affects the SafeImage validation rule and can be used
    | to enforce strict content moderation when required.
    |
    */
    'fail_on_api_error' => env('AZURE_MODERATOR_FAIL_ON_ERROR', false),

    /*
    |--------------------------------------------------------------------------
    | Custom Blocklists
    |--------------------------------------------------------------------------
    |
    | Configure custom blocklists for text moderation. Blocklists allow you
    | to define specific terms or phrases to be flagged in text content.
    |
    */
    'blocklists' => [
        /*
        | Enable or disable blocklist checking globally
        */
        'enabled' => env('AZURE_MODERATOR_BLOCKLISTS_ENABLED', false),

        /*
        | Default blocklists to use for all moderation requests
        | Comma-separated list of blocklist names
        */
        'default_blocklists' => array_filter(
            explode(',', env('AZURE_MODERATOR_DEFAULT_BLOCKLISTS', ''))
        ),

        /*
        | Whether to halt analysis immediately when a blocklist match is found
        | If true, the API will not perform category analysis when blocklist matches
        */
        'halt_on_hit' => env('AZURE_MODERATOR_HALT_ON_BLOCKLIST_HIT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multimodal Analysis (Preview API)
    |--------------------------------------------------------------------------
    |
    | Configure multimodal content analysis for combined text + image moderation.
    | Note: This uses the Azure Content Safety Preview API (2024-09-15-preview).
    |
    */
    'multimodal' => [
        /*
        | Enable or disable multimodal analysis
        */
        'enabled' => env('AZURE_MODERATOR_MULTIMODAL_ENABLED', true),

        /*
        | Whether to enable OCR text extraction from images by default
        */
        'enable_ocr' => env('AZURE_MODERATOR_MULTIMODAL_OCR', true),
    ],
];
