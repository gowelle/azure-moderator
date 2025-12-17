# Azure Content Safety for Laravel

[![Tests](https://github.com/gowelle/azure-moderator/actions/workflows/test.yml/badge.svg)](https://github.com/gowelle/azure-moderator/actions/workflows/test.yml)
[![PHPStan Level 6](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg)](https://phpstan.org/)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/gowelle/azure-moderator.svg)](https://packagist.org/packages/gowelle/azure-moderator)
[![Total Downloads](https://img.shields.io/packagist/dt/gowelle/azure-moderator.svg)](https://packagist.org/packages/gowelle/azure-moderator)

A Laravel package for content moderation using Azure Content Safety API. This package helps you analyze both text and image content for potentially harmful material, automatically flagging or approving content based on Azure's AI-powered analysis.

## Features

- Easy integration with Azure Content Safety API
- **Text and Image content moderation**
- **Multi-Modal Analysis (Batch & Async)**
- **Custom Blocklist Management**
- **Protected Material Detection**
- **Strongly-typed DTO responses** (ModerationResult, CategoryAnalysis)
- Automatic content analysis and flagging
- Configurable severity thresholds
- User rating support (for text moderation)
- Laravel validation rules for text and images
- Artisan commands for testing & management
- Retry handling for API failures
- **Comprehensive test suite (90+ tests)**
- **Integration tests with real Azure API**
- **PHPStan level 6 static analysis**
- **Performance benchmarks**
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
AZURE_MODERATOR_FAIL_ON_ERROR=false
```

### Configuration Options

- `AZURE_CONTENT_SAFETY_ENDPOINT`: Your Azure Content Safety API endpoint URL
- `AZURE_CONTENT_SAFETY_API_KEY`: Your Azure API key (keep this secure!)
- `AZURE_CONTENT_SAFETY_LOW_RATING_THRESHOLD`: Minimum rating to approve text content (0-5, default: 2)
- `AZURE_CONTENT_SAFETY_HIGH_SEVERITY_THRESHOLD`: Minimum severity to flag content (0-7, default: 3)
- `AZURE_MODERATOR_FAIL_ON_ERROR`: Whether validation should fail when API is unavailable (default: false)

## Usage

### Text Moderation

#### Basic Usage

```php
use Gowelle\AzureModerator\Facades\AzureModerator;

// Moderate content - returns ModerationResult DTO
$result = AzureModerator::moderate('Some text content', 4.5);

// Check result using DTO methods
if ($result->isApproved()) {
    // Content is safe
} else {
    // Content was flagged
    $reason = $result->reason;
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

// Moderate image by URL - returns ModerationResult DTO
$result = AzureModerator::moderateImage('https://example.com/image.jpg');

// Check result using DTO methods
if ($result->isApproved()) {
    // Image is safe
} else {
    // Image was flagged
    $reason = $result->reason;
    $scores = $result->categoriesAnalysis; // Array of CategoryAnalysis DTOs
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

**Note:** Base64 images are limited to 4MB of encoded data, which corresponds to approximately 3MB of original image size (due to base64 encoding overhead of ~33%).

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

The package provides flexible error handling to ensure both security and user experience:

```php
use Gowelle\AzureModerator\Exceptions\ModerationException;

try {
    $result = AzureModerator::moderate('Some text content', 4.5);
} catch (ModerationException $e) {
    // Handle API errors (only thrown for input validation errors in moderate())
    Log::error('Moderation failed', [
        'message' => $e->getMessage(),
        'endpoint' => $e->endpoint,
        'status' => $e->statusCode
    ]);
}
```

#### Graceful Degradation and Strict Mode

The `fail_on_api_error` configuration controls how the package behaves when the Azure API is unavailable:

**Default Behavior (fail_on_api_error = false):**
- When the Azure API fails or is unavailable, both `moderate()` and `moderateImage()` return approved status
- The `SafeImage` validation rule passes validation, allowing content through
- This prevents blocking users during API outages
- Best for: Production environments prioritizing user experience

**Strict Mode (fail_on_api_error = true):**
- When the Azure API fails, the `SafeImage` validation rule fails with message: "Unable to validate :attribute safety. Please try again."
- Content cannot be moderated until the API is available
- Best for: High-security environments requiring strict content moderation enforcement

**Configuration:**
```env
# Default: false (graceful degradation)
AZURE_MODERATOR_FAIL_ON_ERROR=false

# Strict mode: true (fail validation on API errors)
AZURE_MODERATOR_FAIL_ON_ERROR=true
```

**Retry Logic:**
The package includes automatic retry logic with exponential backoff for:
- Rate limit errors (429)
- Server errors (500, 503)
- Up to 3 retry attempts per request

### Multi-Modal Analysis (Batch & Async)

Process multiple items or perform context-aware analysis:

```php
// Batch Moderation
$results = AzureModerator::moderateBatch([
    ['type' => 'text', 'content' => 'Comment 1', 'rating' => 4.5],
    ['type' => 'image', 'content' => 'https://example.com/img.jpg'],
]);

// Context-Aware (Text + Image)
$result = AzureModerator::moderateWithContext(
    text: 'Check this out!',
    imageUrl: 'https://example.com/meme.jpg',
    rating: 4.0
);
```

For background processing, dispatch the job:

```php
use Gowelle\AzureModerator\Jobs\ModerateContentJob;

ModerateContentJob::dispatch(
    contentType: 'text',
    content: 'User bio update',
    rating: 4.5,
    metadata: ['user_id' => 123]
);
```

### Custom Blocklists

Manage custom blocklists to filter specific terms.

```bash
# Create and manage lists via CLI
php artisan azure-moderator:blocklist create my-list "Banned words"
php artisan azure-moderator:blocklist add-item my-list "forbidden_term"
```

Use in moderation:

```php
$result = AzureModerator::moderate(
    text: 'Some text',
    rating: 4.5,
    blocklistNames: ['my-list']
);
```

See [Blocklists Guide](docs/BLOCKLISTS.md) for full details.

### Protected Material Detection

Detect copyrighted content in text:

```bash
php artisan azure-moderator:test-protected "Lyrics to a song..."
```

Or use the validation rule:

```php
use Gowelle\AzureModerator\Rules\SafeText;

$request->validate([
    'content' => ['required', new SafeText()],
]);
```

See [Protected Material Guide](docs/PROTECTED_MATERIAL.md) for details.

### Artisan Commands

Test image moderation from the command line:

```bash
# Test image moderation
php artisan azure-moderator:test-image https://example.com/image.jpg

# Test with specific categories
php artisan azure-moderator:test-image https://example.com/image.jpg --categories=Sexual,Violence
```

## Testing

This package includes a comprehensive test suite with unit tests, integration tests, and performance benchmarks.

### Running Tests

```bash
# Run unit tests
composer test

# Run integration tests (requires Azure credentials)
composer test:integration

# Run performance benchmarks
composer test:performance

# Run all tests
composer test:all

# Generate coverage report
composer test-coverage
```

### Integration Tests

Integration tests validate the package against the real Azure Content Safety API. To run them:

1. Copy the example environment file:
   ```bash
   cp .env.integration.example .env.integration
   ```

2. Add your Azure credentials to `.env.integration`:
   ```env
   AZURE_CONTENT_SAFETY_ENDPOINT=https://your-resource.cognitiveservices.azure.com
   AZURE_CONTENT_SAFETY_API_KEY=your-api-key
   ```

3. Run integration tests:
   ```bash
   composer test:integration
   ```

**Test Coverage:**
- 30+ unit tests
- 50+ integration tests (Azure API)
- 10+ performance benchmarks
- **Total: 90+ tests with 100% pass rate**

See [Integration Testing Guide](docs/INTEGRATION_TESTING.md) for detailed documentation.

### Quality Tools

```bash
# Run PHPStan static analysis (level 6)
composer analyse

# Run mutation testing
composer mutate

# Check code style
composer format

# Run all quality checks
composer quality
```

### CI/CD

GitHub Actions automatically runs:
- Unit tests (PHP 8.2 & 8.3)
- Integration tests (when secrets are configured)
- PHPStan static analysis
- Code style checks

To enable integration tests in CI, add these secrets to your repository:
- `AZURE_CONTENT_SAFETY_ENDPOINT`
- `AZURE_CONTENT_SAFETY_API_KEY`

### Documentation

- [Blocklists Guide](docs/BLOCKLISTS.md)
- [Protected Material Guide](docs/PROTECTED_MATERIAL.md)
- [Integration Testing Guide](docs/INTEGRATION_TESTING.md)
- [Performance Testing Guide](docs/PERFORMANCE_TESTING.md)
- [Troubleshooting Guide](docs/TROUBLESHOOTING.md)
- [API Response Examples](docs/API_RESPONSES.md)
- [Roadmap](docs/ROADMAP.md)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email gowelle.john@icloud.com instead of using the issue tracker.

Please review our [Security Policy](SECURITY.md) for more details.

## Credits

- [John Gowelle](https://github.com/gowelle)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
