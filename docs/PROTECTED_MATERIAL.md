# Protected Material Detection

Azure Content Safety for Laravel includes support for detecting protected (copyrighted) material in text content. This feature helps prevent the distribution of copyrighted lyrics, articles, or other intellectual property.

## Overview

The Protected Material Detection feature:
- Identifies known copyrighted text
- Returns a boolean indicating presence
- Can be used via Facade, Service, or Validation Rule

## Configuration

No additional configuration is required beyond the standard Azure credentials. This feature uses a specific endpoint of the Azure Content Safety API.

## CLI Testing

You can easily test text for protected material using the Artisan command:

```bash
php artisan azure-moderator:test-protected "Here are the lyrics to a famous song..."
```

**Output:**
```
Protected Material Analysis Result:
-----------------------------------
Detected: YES
```

## Usage

### 1. Using the Facade (via Container) or Service

Currently, the `ProtectedMaterialService` is registered in the container.

```php
use Gowelle\AzureModerator\ProtectedMaterialService;

$service = app(ProtectedMaterialService::class);

if ($service->detect("Some copyrighted text here")) {
    // Handle protected material
    return response()->json(['error' => 'Copyrighted material detected'], 400);
}
```

### 2. Laravel Validation Rule

The simplest way to integrate this into your application is using the `SafeText` validation rule.

```php
use Gowelle\AzureModerator\Rules\SafeText;

$request->validate([
    'description' => ['required', 'string', new SafeText()],
]);
```

By default, `SafeText` checks for:
1. Harmful content (Hate, Violence, etc. via standard moderation)
2. Protected material (Copyrights)

**Note:** The `SafeText` rule is a composite rule that runs both checks. If either fails, the validation fails.

## Response Details

When using the service directly, `detect()` returns `true` if protected material is found.

Internally, the API returns details like:

```json
{
    "protectedMaterialAnalysis": {
        "detected": true
    }
}
```

This package simplifies this to a boolean for ease of use.
