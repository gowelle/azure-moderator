# Performance Testing Guide

This guide explains how to run and interpret performance benchmarks for the Azure Content Safety Laravel package.

## Running Performance Tests

### Run All Performance Benchmarks

```bash
composer test:performance
```

This runs all performance benchmarks including:
- Text moderation performance tests
- Image moderation performance tests
- Concurrent request handling
- Size limit edge cases

### Run Specific Benchmarks

```bash
# Run only text moderation benchmarks
vendor/bin/pest tests/Performance/TextModerationBenchmark.php

# Run only image moderation benchmarks
vendor/bin/pest tests/Performance/ImageModerationBenchmark.php
```

## Performance Benchmarks

### Text Moderation Performance

**Benchmark**: `benchmark_single_text_moderation_request`
- **Measures**: Average response time for single text moderation requests
- **Iterations**: 10
- **Expected**: < 3000ms average

**Benchmark**: `benchmark_text_moderation_with_all_categories`
- **Measures**: Response time when analyzing all content categories
- **Iterations**: 5
- **Expected**: < 3000ms average

**Benchmark**: `benchmark_concurrent_text_moderation_requests`
- **Measures**: Total time and average per request for concurrent requests
- **Concurrent Requests**: 5
- **Expected**: All requests complete successfully

**Benchmark**: `benchmark_text_moderation_with_varying_content_length`
- **Measures**: Response time impact of content length
- **Content Lengths**: 10, 50, 100, 500, 1000 characters
- **Expected**: < 5000ms for all lengths

**Benchmark**: `measure_retry_logic_overhead`
- **Measures**: Average time without retries
- **Iterations**: 5
- **Expected**: < 3000ms average

### Image Moderation Performance

**Benchmark**: `benchmark_url_based_image_moderation`
- **Measures**: Average response time for URL-based image moderation
- **Iterations**: 10
- **Expected**: < 3000ms average

**Benchmark**: `benchmark_base64_image_moderation`
- **Measures**: Average response time for base64-encoded images
- **Iterations**: 10
- **Expected**: < 3000ms average

**Benchmark**: `compare_url_vs_base64_performance`
- **Measures**: Performance difference between URL and base64 encoding
- **Iterations**: 5 each
- **Expected**: Both < 5000ms average

**Benchmark**: `benchmark_base64_image_size_limits`
- **Measures**: Response time for various image sizes
- **Sizes**: 1KB, 10KB, 100KB, 500KB, 1MB
- **Expected**: All < 10000ms

**Benchmark**: `benchmark_concurrent_image_moderation_requests`
- **Measures**: Total time for concurrent image moderation
- **Concurrent Requests**: 5
- **Expected**: All requests complete successfully

**Benchmark**: `test_4mb_base64_limit_edge_case`
- **Measures**: Performance at 4MB size limit
- **Size**: ~4MB (just under limit)
- **Expected**: Request completes successfully

## Interpreting Results

### Sample Output

```
Text Moderation Performance:
  Iterations: 10
  Average: 1245.67 ms
  Min: 987.23 ms
  Max: 1876.45 ms
```

### Performance Metrics

- **Average**: Mean response time across all iterations
- **Min**: Fastest response time
- **Max**: Slowest response time
- **Total Time**: Sum of all request times
- **Avg per Request**: Average time per request in concurrent tests

### Expected Performance

| Operation | Expected Average | Acceptable Max |
|-----------|-----------------|----------------|
| Text Moderation | < 2000ms | < 3000ms |
| Image Moderation (URL) | < 2000ms | < 3000ms |
| Image Moderation (Base64) | < 2500ms | < 3000ms |
| Concurrent Requests (5) | < 10000ms total | < 15000ms total |

## Performance Optimization Tips

### 1. Use URL-based Image Moderation When Possible

URL-based moderation is typically faster than base64:
- No encoding overhead
- Smaller request payload
- Faster transmission

```php
// Faster
$result = AzureModerator::moderateImage('https://example.com/image.jpg');

// Slower (due to encoding)
$base64 = base64_encode(file_get_contents('image.jpg'));
$result = AzureModerator::moderateImage($base64, encoding: 'base64');
```

### 2. Optimize Image Sizes

- Keep images under 1MB when possible
- Resize images before moderation
- Use appropriate image formats (JPEG for photos, PNG for graphics)

### 3. Batch Requests Efficiently

- Avoid sending too many concurrent requests
- Respect Azure rate limits
- Use queues for background processing

### 4. Cache Results

Consider caching moderation results for frequently checked content:

```php
use Illuminate\Support\Facades\Cache;

$cacheKey = 'moderation_' . md5($content);
$result = Cache::remember($cacheKey, 3600, function () use ($content) {
    return AzureModerator::moderate($content, 4.0);
});
```

### 5. Use Specific Categories

Only analyze categories you need:

```php
// Faster - only checks Sexual and Violence
$result = AzureModerator::moderate($text, 4.0, [
    ContentCategory::SEXUAL->value,
    ContentCategory::VIOLENCE->value,
]);

// Slower - checks all categories
$result = AzureModerator::moderate($text, 4.0);
```

## Factors Affecting Performance

### Network Latency

- Geographic distance to Azure region
- Internet connection speed
- Network congestion

**Solution**: Use Azure region closest to your application

### Content Size

- Larger content takes longer to analyze
- Base64 encoding adds ~33% overhead
- Image resolution affects processing time

**Solution**: Optimize content size before moderation

### API Load

- Azure service load varies
- Peak times may have slower responses
- Rate limiting can add delays

**Solution**: Implement retry logic and queues

### Concurrent Requests

- Too many concurrent requests can overwhelm the API
- May trigger rate limiting
- Can increase overall latency

**Solution**: Limit concurrency to 5-10 requests

## Continuous Performance Monitoring

### 1. Run Benchmarks Regularly

```bash
# Run before releases
composer test:performance

# Log results for comparison
composer test:performance > performance-$(date +%Y%m%d).log
```

### 2. Track Performance Trends

Monitor these metrics over time:
- Average response time
- 95th percentile response time
- Error rate
- Retry frequency

### 3. Set Performance Budgets

Define acceptable performance thresholds:
- Text moderation: < 2000ms
- Image moderation: < 2500ms
- Concurrent requests: < 3000ms per request

### 4. Alert on Regressions

Set up alerts when performance degrades:
- Response time increases by > 50%
- Error rate increases by > 10%
- Retry rate increases significantly

## Troubleshooting Slow Performance

### Issue: High Average Response Time

**Possible Causes**:
- Network latency
- Azure service load
- Large content size

**Solutions**:
1. Check network connectivity
2. Try different Azure region
3. Optimize content size
4. Check Azure service health

### Issue: High Max Response Time

**Possible Causes**:
- Transient network issues
- API rate limiting
- Retry logic triggered

**Solutions**:
1. Review retry logic configuration
2. Implement exponential backoff
3. Monitor Azure rate limits

### Issue: Slow Concurrent Requests

**Possible Causes**:
- Too many concurrent requests
- Rate limiting
- Network bandwidth

**Solutions**:
1. Reduce concurrency level
2. Implement request queuing
3. Use connection pooling

## Additional Resources

- [Integration Testing Guide](INTEGRATION_TESTING.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Azure Content Safety Performance](https://learn.microsoft.com/en-us/azure/ai-services/content-safety/overview)
