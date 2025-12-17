# Contributing

Thank you for considering contributing to the Azure Content Safety Laravel package! This document outlines the guidelines and process for contributing.

---

## Development Environment

1. Fork the repository
2. Clone your fork locally
3. Install dependencies:
```bash
composer install
```
4. Create a branch for your changes:
```bash
git checkout -b feature/your-feature-name
```

---

## Testing

We use Pest PHP for testing. Before submitting a pull request, please ensure all tests pass:

```bash
composer test
```

### Test Coverage

When adding features or fixing bugs, please ensure your changes include tests covering:

- **Happy path scenarios:** Valid inputs producing expected results
- **Error scenarios:** API failures, network issues, invalid inputs
- **Configuration variations:** Test both `fail_on_api_error` true and false states
- **Graceful degradation:** Verify API errors are handled appropriately
- **Edge cases:** Large inputs, special characters, boundary values

### Example Test Patterns

```php
// Test graceful degradation (fail_on_api_error = false)
test('moderateImage returns approved on API failure with fail_on_api_error false')
    ->expect(...);

// Test strict mode (fail_on_api_error = true)
test('SafeImage validation fails on API error with fail_on_api_error true')
    ->expect(...);

// Test retry logic
test('Service retries on transient errors (429, 500, 503)')
    ->expect(...);
```

To add new tests, create a file in the `tests` directory following the existing test patterns.

---

## Coding Standards

This package follows the PSR-12 coding standard. We use Laravel Pint for code style enforcement:

```bash
composer format
```

---

## Pull Request Process

1. Update the README.md with details of changes to the interface, if applicable
2. Add any new tests that cover your changes
3. Ensure the test suite passes
4. Update the CHANGELOG.md with a note describing your changes
5. Submit the pull request with a clear title and description

---

## Bug Reports

When filing an issue, make sure to include:

- Package version
- PHP version
- Laravel version
- A clear description of the issue
- Steps to reproduce
- Expected behavior
- Actual behavior

---

## Feature Requests

Feature requests are welcome! Please provide:

- A clear description of the feature
- Use cases and benefits
- Any potential drawbacks
- Implementation ideas (if any)

---

## Security Vulnerabilities

If you discover a security vulnerability, please send an email to [gowelle.john@icloud.com](mailto:gowelle.john@icloud.com) instead of using the issue tracker. See [SECURITY.md](SECURITY.md) for more details.

---

## Code of Conduct

This project adheres to a Code of Conduct. By participating in this project, you are expected to uphold this code.

---

## License

By contributing to this package, you agree that your contributions will be licensed under its MIT License.