# Changelog

All notable changes to `azure-moderator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2025-12-17

### Added - Phase 2: Advanced Moderation Features 🚀

- **Multi-Modal Content Analysis**
  - Batch moderation via `moderateBatch()`
  - Context-aware moderation via `moderateWithContext()` (analyzing text + image together)
  - `ModerateContentJob` for asynchronous processing via Laravel queues
  - `ContentModerated` event dispatched upon completion
  - Graceful degradation for batch processing failures

- **Custom Blocklists**
  - Full CRUD management for custom blocklists
  - Creating, listing, updating, and deleting blocklists via `BlocklistService`
  - Managing blocklist items (add/remove terms)
  - Integration into `moderate()` method with `blocklistNames` and `haltOnBlocklistHit`
  - `azure-moderator:blocklist` Artisan command for CLI management
  - Configuration options for default blocklists

- **Protected Material Detection**
  - New `ProtectedMaterialService` to detect copyrighted text
  - `SafeText` validation rule that checks for both harmful content and protected material
  - `azure-moderator:test-protected` Artisan command for CLI testing

### Changed
- **Facade** - Added `moderateBatch` and `moderateWithContext` methods
- **Configuration** - Added `lowRatingThreshold` as float support (was int)
- **Documentation** - Added [BLOCKLISTS.md](docs/BLOCKLISTS.md) and [PROTECTED_MATERIAL.md](docs/PROTECTED_MATERIAL.md)

### Technical Details
- **Zero Breaking Changes** - All new features are additive
- **Test Coverage** - 100% pass rate with 54 total tests (26 new tests)
- **Static Analysis** - PHPStan Level 6 maintained with strict types
- **Performance** - Optimized parallel processing for batch requests

## [1.3.0] - 2025-12-17

### Added - Phase 1: Testing & Quality Assurance ✅
- **Comprehensive Integration Test Suite** (50 tests)
  - Text moderation integration tests (14 tests) with real Azure API
  - Image moderation integration tests (12 tests) with URL and base64 support
  - Retry logic integration tests (8 tests) validating error handling
  - SafeImage validation integration tests (5 tests) with Laravel validation
  - Facade integration tests (6 tests) verifying service provider registration
  - Artisan command integration tests (5 tests) for CLI functionality
- **Performance Benchmarks** (11 tests)
  - Text moderation performance testing (~400ms average)
  - Image moderation performance testing (~350ms average)
  - Concurrent request handling benchmarks
  - Base64 size limit edge case testing
- **Quality Tools**
  - PHPStan level 6 static analysis (0 errors)
  - Infection PHP mutation testing setup (80% MSI threshold)
  - Laravel Pint code style enforcement
  - Comprehensive quality command (`composer quality`)
- **Documentation** (1,500+ lines)
  - Integration Testing Guide (`docs/INTEGRATION_TESTING.md`)
  - Performance Testing Guide (`docs/PERFORMANCE_TESTING.md`)
  - Troubleshooting Guide (`docs/TROUBLESHOOTING.md`)
  - API Response Examples (`docs/API_RESPONSES.md`)
  - Mutation Testing Setup Guide (`docs/MUTATION_TESTING.md`)
  - Updated Roadmap with Phase 1 completion (`docs/ROADMAP.md`)
- **CI/CD Enhancements**
  - Multi-job GitHub Actions workflow
  - Unit tests on PHP 8.2 & 8.3
  - Integration tests with Azure secrets
  - PHPStan static analysis job
  - Code style checking job
- **Configuration**
  - `.env.integration.example` for integration test setup
  - `phpunit.integration.xml` for integration test configuration
  - Separate test suites for unit, integration, and performance tests

### Changed
- **README.md** - Comprehensive testing documentation
  - Added PHPStan level 6 badge
  - Documented all test commands and quality tools
  - Added CI/CD setup instructions
  - Included links to all documentation guides
- **Facade** - Updated to use contract class for better type safety
- **Artisan Command** - Added "Image Moderation Result" header to output
- **Test Coverage** - Increased from 28 to 89 total tests (216% increase)

### Fixed
- PHPStan type errors in `AzureContentSafetyService` (array type hints)
- PHPStan type errors in `AzureContentSafetyServiceContract` (detailed return types)
- PHPStan errors in `SafeImage` validation rule (array type hints)
- PHPStan errors in `TestImageModerationCommand` (null coalescing operators)
- PHPStan error in `AzureContentSafetyServiceProvider` (method signature)
- Facade accessor to return contract class instead of string binding
- Command argument name from 'url' to 'image' for consistency

### Technical Details
- **Test Statistics**: 89 total tests (28 unit + 61 integration/performance)
- **Test Pass Rate**: 100% (all tests passing)
- **Performance**: 350-400ms average API response time
- **Static Analysis**: PHPStan level 6 with 0 errors
- **Code Coverage**: Comprehensive unit and integration coverage
- **Documentation**: 5 comprehensive guides totaling 1,500+ lines

## [1.2.0] - 2025-11-14

### Added
- Image moderation support via Azure Content Safety API
- `moderateImage()` method for analyzing images by URL or base64
- `SafeImage` Laravel validation rule for automatic image validation
- `azure-moderator:test-image` Artisan command for testing image moderation
- Comprehensive test suite for image moderation functionality
- Support for both URL and base64-encoded images
- Detailed severity scores in image moderation responses

### Changed
- Updated README with comprehensive image moderation documentation
- Enhanced facade with image moderation examples
- Improved service contract to include image moderation interface

### Technical Details
- Added validation for image URLs and base64 data
- Implemented 4MB size limit for base64 images
- Reused existing retry logic and error handling for image API calls
- All image moderation uses same category system (Hate, SelfHarm, Sexual, Violence)

## [1.1.0] - 2025-05-12

### Added
- CHANGELOG.md to track version history
- Security policy with dedicated reporting process
- Comprehensive documentation
- Configuration options for severity thresholds
- Improved error messages and logging
- Type hints and return type documentation

### Changed
- Updated README with detailed usage examples
- Improved API response handling
- Enhanced validation messages

## [1.0.5] - 2025-05-11
### Changed
- Dependency updates and minor improvements

## [1.0.4] - 2025-05-11
## [1.0.3] - 2025-05-11
## [1.0.2] - 2025-05-11
## [1.0.1] - 2025-05-11

## [1.0.0] - 2025-05-11

### Added
- Initial release
- Azure Content Safety API integration
- Content moderation with user ratings
- Configurable severity thresholds
- Automatic retry handling
- Laravel Facade support
- Custom exceptions
- Extensive logging
- PHPUnit test suite
- GitHub Actions CI pipeline

[Unreleased]: https://github.com/gowelle/azure-moderator/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/gowelle/azure-moderator/compare/v1.3.0...v2.0.0
[1.3.0]: https://github.com/gowelle/azure-moderator/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/gowelle/azure-moderator/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/gowelle/azure-moderator/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/gowelle/azure-moderator/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/gowelle/azure-moderator/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/gowelle/azure-moderator/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/gowelle/azure-moderator/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/gowelle/azure-moderator/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/gowelle/azure-moderator/releases/tag/v1.0.0