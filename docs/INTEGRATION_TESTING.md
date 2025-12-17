# Integration Testing Guide

This guide explains how to run integration tests for the Azure Content Safety Laravel package. Integration tests validate the package against the actual Azure Content Safety API to ensure real-world compatibility.

## Prerequisites

### 1. Azure Content Safety API Credentials

You need valid Azure Content Safety API credentials to run integration tests:

1. **Create an Azure Content Safety resource** in the [Azure Portal](https://portal.azure.com)
2. **Get your endpoint URL** (e.g., `https://your-resource-name.cognitiveservices.azure.com`)
3. **Get your API key** from the "Keys and Endpoint" section

### 2. Environment Setup

Copy the example environment file:

```bash
cp .env.integration.example .env.integration
```

Edit `.env.integration` and add your Azure credentials:

```env
AZURE_CONTENT_SAFETY_ENDPOINT=https://your-resource-name.cognitiveservices.azure.com
AZURE_CONTENT_SAFETY_API_KEY=your-api-key-here
```

> [!WARNING]
> **API Costs**: Integration tests make real API calls to Azure Content Safety, which may incur costs based on your Azure pricing tier. Tests are designed to be minimal and efficient.

## Running Integration Tests

### Run All Integration Tests

```bash
composer test:integration
```

This runs all integration tests including:
- Text moderation tests
- Image moderation tests
- Retry logic tests
- Laravel end-to-end tests (validation, facade, commands)

### Run Specific Test Suites

```bash
# Run only text moderation integration tests
vendor/bin/pest tests/Integration/TextModerationIntegrationTest.php

# Run only image moderation integration tests
vendor/bin/pest tests/Integration/ImageModerationIntegrationTest.php

# Run only retry logic tests
vendor/bin/pest tests/Integration/RetryLogicIntegrationTest.php
```

### Run Without Credentials

If Azure credentials are not configured, integration tests will be automatically skipped:

```bash
composer test:integration
# Output: Tests: 0 skipped, 0 passed
```

This ensures the test suite can run in CI/CD environments without credentials.

## Test Structure

### Azure API Integration Tests

Located in `tests/Integration/`:

- **`TextModerationIntegrationTest.php`** - Tests text moderation with real Azure API
  - All content categories (Hate, SelfHarm, Sexual, Violence)
  - Various severity thresholds
  - Different rating values
  - Edge cases (empty text, long text, special characters, unicode)

- **`ImageModerationIntegrationTest.php`** - Tests image moderation with real Azure API
  - URL-based image moderation
  - Base64-encoded image moderation
  - All content categories
  - Various image formats and sizes

- **`RetryLogicIntegrationTest.php`** - Tests retry logic and error handling
  - Invalid credentials handling
  - Invalid endpoint handling
  - Concurrent request handling
  - Performance measurement

### Laravel End-to-End Tests

- **`SafeImageValidationIntegrationTest.php`** - Tests SafeImage validation rule
  - Real file upload scenarios
  - Custom categories
  - Graceful degradation vs strict mode

- **`FacadeIntegrationTest.php`** - Tests AzureModerator facade
  - Service provider registration
  - Configuration loading
  - Facade methods

- **`ArtisanCommandIntegrationTest.php`** - Tests Artisan commands
  - `azure-moderator:test-image` command
  - Command options and error handling

## CI/CD Integration

### GitHub Actions

Integration tests can run in GitHub Actions using repository secrets:

1. Add secrets to your repository:
   - `AZURE_CONTENT_SAFETY_ENDPOINT`
   - `AZURE_CONTENT_SAFETY_API_KEY`

2. Update `.github/workflows/test.yml`:

```yaml
jobs:
  integration-tests:
    runs-on: ubuntu-latest
    if: ${{ secrets.AZURE_CONTENT_SAFETY_API_KEY != '' }}
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
      - name: Install Dependencies
        run: composer install
      - name: Run Integration Tests
        env:
          AZURE_CONTENT_SAFETY_ENDPOINT: ${{ secrets.AZURE_CONTENT_SAFETY_ENDPOINT }}
          AZURE_CONTENT_SAFETY_API_KEY: ${{ secrets.AZURE_CONTENT_SAFETY_API_KEY }}
        run: composer test:integration
```

## Best Practices

### 1. Test Data

- Use neutral, safe content for testing
- Avoid testing with actual harmful content
- Use placeholder images from services like `via.placeholder.com`

### 2. Rate Limiting

- Azure Content Safety has rate limits
- Run tests sequentially to avoid hitting limits
- Use delays between tests if needed

### 3. Cost Management

- Integration tests are designed to be minimal
- Typical test run: ~50 API calls
- Monitor your Azure usage in the portal

### 4. Test Isolation

- Each test is independent
- Tests don't depend on each other
- Tests can run in any order

## Writing Integration Tests

### Example Test

```php
<?php

namespace Tests\Integration;

use Gowelle\AzureModerator\Data\ModerationResult;
use Gowelle\AzureModerator\Enums\ModerationStatus;
use Gowelle\AzureModerator\Facades\AzureModerator;
use Tests\TestCase;

class MyIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip if credentials not configured
        if (!env('AZURE_CONTENT_SAFETY_ENDPOINT') || !env('AZURE_CONTENT_SAFETY_API_KEY')) {
            $this->markTestSkipped('Azure credentials not configured');
        }

        config([
            'azure-moderator.endpoint' => env('AZURE_CONTENT_SAFETY_ENDPOINT'),
            'azure-moderator.api_key' => env('AZURE_CONTENT_SAFETY_API_KEY'),
        ]);
    }

    /** @test */
    public function it_moderates_content(): void
    {
        $result = AzureModerator::moderate('Test content', 4.0);

        // Assert DTO type and properties
        expect($result)
            ->toBeInstanceOf(ModerationResult::class)
            ->and($result->isApproved())->toBeTrue()
            ->and($result->status)->toBe(ModerationStatus::APPROVED);
    }
}
```

### Guidelines

1. **Always skip when credentials are missing**
   ```php
   if (!env('AZURE_CONTENT_SAFETY_ENDPOINT') || !env('AZURE_CONTENT_SAFETY_API_KEY')) {
       $this->markTestSkipped('Azure credentials not configured');
   }
   ```

2. **Use descriptive test names**
   ```php
   public function it_moderates_text_with_all_categories(): void
   ```

3. **Test real-world scenarios**
   - Actual API responses
   - Error handling
   - Edge cases

4. **Keep tests fast**
   - Minimize API calls
   - Use efficient test data

## Troubleshooting

### Tests Are Skipped

**Cause**: Azure credentials not configured

**Solution**: Set up `.env.integration` with valid credentials

### Authentication Errors

**Cause**: Invalid API key or endpoint

**Solution**: Verify credentials in Azure Portal

### Rate Limit Errors

**Cause**: Too many requests in short time

**Solution**: Wait a few minutes and retry

### Network Timeouts

**Cause**: Network connectivity issues

**Solution**: Check internet connection and Azure service status

## Additional Resources

- [Azure Content Safety Documentation](https://learn.microsoft.com/en-us/azure/ai-services/content-safety/)
- [Performance Testing Guide](PERFORMANCE_TESTING.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [API Response Examples](API_RESPONSES.md)
