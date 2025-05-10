# Azure Content Safety for Laravel

[![Tests](https://github.com/gowelle/azure-moderator/actions/workflows/test.yml/badge.svg)](https://github.com/gowelle/azure-moderator/actions/workflows/test.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/gowelle/azure-moderator.svg)](https://packagist.org/packages/gowelle/azure-moderator)
[![Total Downloads](https://img.shields.io/packagist/dt/gowelle/azure-moderator.svg)](https://packagist.org/packages/gowelle/azure-moderator)

A Laravel package for content moderation using Azure Content Safety API.

## Installation

You can install the package via composer:

```bash
composer require gowelle/azure-moderator
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --provider="Gowelle\AzureModerator\AzureContentSafetyServiceProvider"
```

Add your Azure credentials to your `.env` file:

```env
AZURE_CONTENT_SAFETY_ENDPOINT=your-endpoint
AZURE_CONTENT_SAFETY_API_KEY=your-api-key
```

## Usage

```php
use Gowelle\AzureModerator\Facades\AzureModerator;

// Moderate content
$result = AzureModerator::moderate($content, $rating);

// Check result
if ($result['status'] === 'approved') {
    // Content is safe
} else {
    // Content is flagged
    $reason = $result['reason'];
}
```

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [John Gowelle](https://github.com/gowelle)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
