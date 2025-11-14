# Changelog

All notable changes to `azure-moderator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/gowelle/azure-moderator/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/gowelle/azure-moderator/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/gowelle/azure-moderator/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/gowelle/azure-moderator/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/gowelle/azure-moderator/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/gowelle/azure-moderator/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/gowelle/azure-moderator/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/gowelle/azure-moderator/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/gowelle/azure-moderator/releases/tag/v1.0.0