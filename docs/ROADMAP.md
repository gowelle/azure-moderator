# Azure Content Safety for Laravel - Roadmap

This document outlines the development roadmap for the Azure Content Safety Laravel package. The roadmap is organized into phases, with each phase containing specific features, improvements, and milestones.

---

## Current Status (v2.0.0 - Phase 2 In Progress)

### ✅ Completed Features
- Text content moderation with Azure Content Safety API
- Image moderation (URL and base64)
- **Custom Blocklists & Protected Material Detection**
- **Multi-Modal & Batch Moderation**
- **Strict Data Transfer Objects (DTOs) for all responses**
- Configurable severity thresholds
- User rating support for text moderation
- `SafeImage` & `SafeText` Laravel validation rules
- Automatic retry logic with exponential backoff
- Graceful degradation vs. strict validation modes
- Artisan commands for testing & management
- Comprehensive unit test coverage
- **Comprehensive integration test suite (70+ tests)**
- **Performance benchmarks**
- **Detailed documentation (6 guides)**
- GitHub Actions CI pipeline

### 🎉 Recently Completed (v2.0.0)
- ✅ **DTO Refactoring**: strict typing with `ModerationResult`, `Blocklist`, etc.
- ✅ **Custom Blocklists**: Create, manage, and use blocklists
- ✅ **Protected Material**: Detect copyrighted text
- ✅ **Multi-Modal**: Batch analysis and async jobs
- ✅ Integration tests with real Azure API
- ✅ Performance benchmarking

---

## Phase 1: Testing & Quality Assurance ✅ COMPLETED (December 2025)

### 🎯 Goal
Establish comprehensive testing infrastructure including integration tests to ensure reliability and maintainability.

### Integration Tests ✅
> [!NOTE]
> All integration tests validated against the actual Azure Content Safety API with 100% pass rate.

- [x] **Azure API Integration Tests** (34 tests)
  - [x] Create integration test suite for text moderation endpoint (14 tests)
  - [x] Create integration test suite for image moderation endpoint (12 tests)
  - [x] Test all content categories (Hate, SelfHarm, Sexual, Violence)
  - [x] Test various severity thresholds
  - [x] Validate retry logic with rate limiting scenarios (8 tests)
  - [x] Test graceful degradation behavior with API failures

- [x] **End-to-End Laravel Integration Tests** (16 tests)
  - [x] Test `SafeImage` validation rule in real Laravel request context (5 tests)
  - [x] Test facade usage in Laravel application (6 tests)
  - [x] Test service provider registration and configuration
  - [x] Test Artisan commands with real API calls (5 tests)

- [x] **Performance & Load Testing** (11 tests)
  - [x] Benchmark text moderation performance (~400ms average)
  - [x] Benchmark image moderation performance (~350ms average)
  - [x] Test concurrent request handling (~360ms per request)
  - [x] Measure retry logic overhead
  - [x] Test base64 image size limits (4MB encoded)

### Quality Improvements ✅
- [x] Increase unit test coverage to 95%+ (28 unit tests)
- [x] Add mutation testing with Infection PHP (80% MSI threshold)
- [x] Implement PHPStan level 6 static analysis (0 errors)
- [x] Add code quality badges to README
- [x] Create automated test reporting

### Documentation ✅
- [x] Create integration testing guide (INTEGRATION_TESTING.md)
- [x] Document test environment setup (.env.integration.example)
- [x] Add troubleshooting guide for common issues (TROUBLESHOOTING.md)
- [x] Create API response examples documentation (API_RESPONSES.md)
- [x] Performance testing guide (PERFORMANCE_TESTING.md)

### 📊 Phase 1 Results
- **Total Tests**: 89 tests (28 unit + 61 integration/performance)
- **Test Pass Rate**: 100%
- **PHPStan**: Level 6, 0 errors
- **Performance**: 350-400ms average response time
- **Documentation**: 1,500+ lines across 4 guides

---

## Phase 2: Enhanced Moderation Features (Started Q4 2025)

### 🎯 Goal
Expand moderation capabilities with advanced Azure Content Safety features.

### Video Moderation
- [ ] Add video content moderation support
  - [ ] Support video URL analysis
  - [ ] Support video file upload (multipart)
  - [ ] Frame-by-frame analysis results
  - [ ] Timestamp-based flagging
  - [ ] `SafeVideo` validation rule
  - [ ] Artisan command for video testing

### Multi-Modal Content Analysis
- [x] Support combined text + image analysis
- [x] Contextual moderation (text with image context)
- [x] Batch moderation for multiple items
- [x] Asynchronous moderation with job queues

### Custom Blocklists
- [x] Integrate Azure Custom Blocklists API
  - [x] Create and manage custom blocklists
  - [x] Add/remove terms from blocklists
  - [x] Apply blocklists to text moderation
  - [x] Blocklist management Artisan commands

### Protected Material Detection
- [x] Integrate Azure Protected Material Detection
  - [x] Detect copyrighted text content
  - [ ] Detect copyrighted images
  - [ ] Custom protected material lists

---

## Phase 3: Developer Experience & Tooling (Q3 2026)

### 🎯 Goal
Improve developer experience with better tooling, debugging, and monitoring capabilities.

### Enhanced Debugging
- [ ] **Moderation Dashboard**
  - [ ] Create web-based moderation history viewer
  - [ ] Display moderation results with visual scores
  - [ ] Filter and search moderation history
  - [ ] Export moderation reports

- [ ] **Logging & Monitoring**
  - [ ] Structured logging with context
  - [ ] Integration with Laravel Telescope
  - [ ] Metrics collection (success rate, latency, etc.)
  - [ ] Alert system for high rejection rates

### Developer Tools
- [ ] **Artisan Commands**
  - [ ] `azure-moderator:analyze` - Analyze content from CLI
  - [ ] `azure-moderator:stats` - Show moderation statistics
  - [ ] `azure-moderator:config` - Validate configuration
  - [ ] `azure-moderator:benchmark` - Performance benchmarking

- [ ] **Testing Utilities**
  - [ ] Mock Azure API responses for testing
  - [ ] Fake moderation service for local development
  - [ ] Test data generators for various content types
  - [ ] Assertion helpers for PHPUnit/Pest

### Configuration Enhancements
- [ ] Per-environment configuration profiles
- [ ] Dynamic threshold adjustment based on context
- [ ] Category-specific severity thresholds
- [ ] Custom callback hooks for moderation results

---

## Phase 4: Performance & Scalability (Q4 2026)

### 🎯 Goal
Optimize performance and support high-volume production environments.

### Caching & Optimization
- [ ] **Response Caching**
  - [ ] Cache moderation results with configurable TTL
  - [ ] Cache invalidation strategies
  - [ ] Support for Redis, Memcached, and database caching
  - [ ] Cache warming for common content

- [ ] **Request Optimization**
  - [ ] Request batching for multiple items
  - [ ] Connection pooling for HTTP client
  - [ ] Lazy loading for large images
  - [ ] Streaming support for video content

### Queue Integration
- [ ] **Asynchronous Processing**
  - [ ] Queue jobs for background moderation
  - [ ] Webhook support for async results
  - [ ] Priority queue for urgent moderation
  - [ ] Failed job handling and retry logic

### Rate Limiting & Throttling
- [ ] Client-side rate limiting
- [ ] Intelligent request throttling
- [ ] Circuit breaker pattern for API failures
- [ ] Fallback strategies during outages

---

## Phase 5: Enterprise Features (Q1 2027)

### 🎯 Goal
Add enterprise-grade features for large-scale deployments.

### Multi-Tenancy Support
- [ ] Per-tenant Azure credentials
- [ ] Tenant-specific configuration
- [ ] Isolated moderation history
- [ ] Tenant-level analytics

### Advanced Reporting
- [ ] **Analytics Dashboard**
  - [ ] Moderation trends over time
  - [ ] Category-wise breakdown
  - [ ] User behavior analysis
  - [ ] Compliance reporting

- [ ] **Export & Integration**
  - [ ] CSV/JSON export of moderation data
  - [ ] Integration with BI tools
  - [ ] Webhook notifications for moderation events
  - [ ] API for external reporting tools

### Compliance & Audit
- [ ] Audit trail for all moderation decisions
- [ ] GDPR compliance features
  - [ ] Data retention policies
  - [ ] Right to be forgotten
  - [ ] Data export for users
- [ ] SOC 2 compliance documentation
- [ ] Configurable data residency

### Custom Workflows
- [ ] Human review workflow integration
- [ ] Multi-stage moderation pipelines
- [ ] Appeal and override mechanisms
- [ ] Custom action triggers based on results

---

## Phase 6: Ecosystem & Extensions (Q2 2027)

### 🎯 Goal
Build ecosystem around the package with extensions and integrations.

### Framework Integrations
- [ ] **Laravel Ecosystem**
  - [ ] Laravel Nova integration (admin panel)
  - [ ] Filament plugin for moderation management
  - [ ] Livewire components for real-time moderation
  - [ ] Inertia.js components for SPAs

- [ ] **Third-Party Services**
  - [ ] Integration with Laravel Media Library
  - [ ] Cloudinary integration for image moderation
  - [ ] AWS S3 integration for video moderation
  - [ ] CDN integration for cached results

### Community Extensions
- [ ] Plugin system for custom analyzers
- [ ] Community-contributed validation rules
- [ ] Preset configurations for common use cases
- [ ] Template library for moderation policies

### Documentation & Education
- [ ] Video tutorials and screencasts
- [ ] Interactive documentation with examples
- [ ] Case studies from production deployments
- [ ] Best practices guide
- [ ] Migration guides for major versions

---

## Long-Term Vision (2027+)

### AI & Machine Learning
- [ ] Local ML model integration for offline moderation
- [ ] Custom model training with Azure ML
- [ ] Sentiment analysis integration
- [ ] Language detection and translation

### Advanced Content Types
- [ ] Audio content moderation
- [ ] Live stream moderation
- [ ] AR/VR content analysis
- [ ] Code snippet moderation (for developer platforms)

### Global Expansion
- [ ] Multi-language support for error messages
- [ ] Localized documentation
- [ ] Region-specific Azure endpoints
- [ ] Cultural context awareness

---

## Contributing to the Roadmap

We welcome community input on the roadmap! If you have suggestions for features or improvements:

1. **Open an Issue**: Describe your feature request with use cases
2. **Join Discussions**: Participate in roadmap planning discussions
3. **Submit PRs**: Contribute code for roadmap items
4. **Share Feedback**: Let us know what features matter most to you

### Priority Guidelines

Features are prioritized based on:
- **Community demand** - Most requested features
- **Security & compliance** - Critical for production use
- **Performance impact** - Significant improvements
- **Azure API updates** - New capabilities from Azure
- **Breaking changes** - Grouped into major versions

---

## Version Planning

### v1.3.0 (Q1 2026)
- Integration test suite
- Enhanced error handling
- Performance benchmarking

### v2.0.0 (Q4 2025)
- Custom blocklists
- Protected material detection
- Multi-modal & batch analysis
- DTO refactoring (Breaking Change)

### v2.1.0 (Q1 2026)
- Video moderation
- Integration with Laravel Media Library

### v2.2.0 (Q3 2026)
- Moderation dashboard
- Enhanced Artisan commands
- Testing utilities

### v2.3.0 (Q4 2026)
- Response caching
- Queue integration
- Performance optimizations

### v3.0.0 (Q1 2027)
- Multi-tenancy support
- Advanced reporting
- Enterprise features

---

## Maintenance & Support

### Long-Term Support (LTS)
- **v1.x**: Supported until Q2 2026
- **v2.x**: Supported until Q2 2027
- **v3.x**: Supported until Q2 2028

### Security Updates
- Critical security patches released immediately
- Regular dependency updates
- Automated vulnerability scanning

### Compatibility
- PHP 8.2+ support maintained
- Laravel 10+ support maintained
- Azure Content Safety API v1.0 compatibility
- Backward compatibility within major versions

---

## Get Involved

- **GitHub**: [gowelle/azure-moderator](https://github.com/gowelle/azure-moderator)
- **Issues**: Report bugs or request features
- **Discussions**: Share ideas and get help
- **Email**: gowelle.john@icloud.com

---

*Last Updated: December 17, 2025*
*Next Review: March 2026*
