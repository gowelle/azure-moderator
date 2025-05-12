# Azure Content Safety for Laravel

[![Tests](https://github.com/gowelle/azure-moderator/actions/workflows/test.yml/badge.svg)](https://github.com/gowelle/azure-moderator/actions/workflows/test.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/gowelle/azure-moderator.svg)](https://packagist.org/packages/gowelle/azure-moderator)
[![Total Downloads](https://img.shields.io/packagist/dt/gowelle/azure-moderator.svg)](https://packagist.org/packages/gowelle/azure-moderator)

A Laravel package for content moderation using Azure Content Safety API. This package helps you analyze text content for potentially harmful content and automatically flag or approve content based on Azure's analysis and user ratings.

## Features

- Easy integration with Azure Content Safety API
- Automatic content analysis and flagging
- Configurable severity thresholds
- User rating support
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

### Basic Usage

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

### Custom Categories

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

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@gowelle.com instead of using the issue tracker.

Please review our [Security Policy](SECURITY.md) for more details.

## Credits

- [Your Name](https://github.com/gowelle)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
