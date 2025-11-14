# Azure Content Safety for Laravel

[![Tests](https://github.com/gowelle/azure-moderator/actions/workflows/test.yml/badge.svg)](https://github.com/gowelle/azure-moderator/actions/workflows/test.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/gowelle/azure-moderator.svg)](https://packagist.org/packages/gowelle/azure-moderator)
[![Total Downloads](https://img.shields.io/packagist/dt/gowelle/azure-moderator.svg)](https://packagist.org/packages/gowelle/azure-moderator)

A Laravel package for content moderation using Azure Content Safety API. This package helps you analyze both text and image content for potentially harmful material, automatically flagging or approving content based on Azure's AI-powered analysis.

## Features

- Easy integration with Azure Content Safety API
- **Text and Image content moderation**
- Automatic content analysis and flagging
- Configurable severity thresholds
- User rating support (for text moderation)
- Laravel validation rules for images
- Artisan command for testing
- Retry handling for API failures
- Laravel-native configuration
- Extensive logging

## Requirements

- PHP 8.2 or higher
- Laravel 10.0 or higher
- Azure Content Safety API subscription

## Installation

Install the package via composer:

```bash
composer require gowelle/azure-moderator
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Gowelle\AzureModerator\AzureContentSafetyServiceProvider"
```

## Configuration

Add your Azure credentials to your `.env` file:

```env
AZURE_CONTENT_SAFETY_ENDPOINT=your-endpoint
AZURE_CONTENT_SAFETY_API_KEY=your-api-key
AZURE_CONTENT_SAFETY_LOW_RATING_THRESHOLD=2
AZURE_CONTENT_SAFETY_HIGH_SEVERITY_THRESHOLD=6
```

## Usage

### Text Moderation

#### Basic Usage

```php
use Gowelle\AzureModerator\Facades\AzureModerator;

// Moderate content
$result = AzureModerator::moderate('Some text content', 4.5);

// Check result
if ($result['status'] === 'approved') {
    // Content is safe
} else {
    // Content was flagged
    $reason = $result['reason'];
}
```

#### Custom Categories

```php
use Gowelle\AzureModerator\Enums\ContentCategory;

$result = AzureModerator::moderate(
    text: 'Some text content',
    rating: 4.5,
    categories: [
        ContentCategory::HATE->value,
        ContentCategory::VIOLENCE->value
    ]
);
```

### Image Moderation

#### Basic Image Moderation

```php
use Gowelle\AzureModerator\Facades\AzureModerator;

// Moderate image by URL
$result = AzureModerator::moderateImage('https://example.com/image.jpg');

// Check result
if ($result['status'] === 'approved') {
    // Image is safe
} else {
    // Image was flagged
    $reason = $result['reason'];
    $scores = $result['scores']; // Detailed severity scores
}
```

#### Base64 Image Moderation

```php
// Moderate uploaded image
$imageData = file_get_contents($uploadedFile->getRealPath());
$base64Image = base64_encode($imageData);

$result = AzureModerator::moderateImage(
    image: $base64Image,
    encoding: 'base64'
);
```

#### Image Moderation with Custom Categories

```php
use Gowelle\AzureModerator\Enums\ContentCategory;

$result = AzureModerator::moderateImage(
    image: 'https://example.com/image.jpg',
    categories: [
        ContentCategory::SEXUAL->value,
        ContentCategory::VIOLENCE->value
    ]
);
```

### Laravel Validation

Use the `SafeImage` validation rule to automatically validate uploaded images:

```php
use Gowelle\AzureModerator\Rules\SafeImage;

// In your form request or controller
$request->validate([
    'avatar' => ['required', 'image', 'max:2048', new SafeImage()],
]);

// With custom categories
$request->validate([
    'profile_picture' => [
        'required',
        'image',
        new SafeImage([
            ContentCategory::SEXUAL->value,
            ContentCategory::VIOLENCE->value
        ])
    ],
]);
```

### Error Handling

```php
use Gowelle\AzureModerator\Exceptions\ModerationException;

try {
    $result = AzureModerator::moderate('Some text content', 4.5);
} catch (ModerationException $e) {
    // Handle API errors
    Log::error('Moderation failed', [
        'message' => $e->getMessage(),
        'endpoint' => $e->endpoint,
        'status' => $e->statusCode
    ]);
}
```

### Artisan Commands

Test image moderation from the command line:

```bash
# Test image moderation
php artisan azure-moderator:test-image https://example.com/image.jpg

# Test with specific categories
php artisan azure-moderator:test-image https://example.com/image.jpg --categories=Sexual,Violence
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@gowelle.com instead of using the issue tracker.

Please review our [Security Policy](SECURITY.md) for more details.

## Credits

- [John Gowelle](https://github.com/gowelle)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
