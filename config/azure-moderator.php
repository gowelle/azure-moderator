<?php

return [
        'endpoint' => env('AZURE_CONTENT_MODERATOR_ENDPOINT'),
        'api_key' => env('AZURE_CONTENT_MODERATOR_KEY'),
        'language' => env('AZURE_CONTENT_MODERATOR_LANGUAGE', 'swa'),
];
