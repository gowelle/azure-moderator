# Troubleshooting Guide

This guide helps you diagnose and resolve common issues with the Azure Content Safety Laravel package.

## Common Issues

### Authentication Errors

#### Issue: "Invalid API Key" or "Unauthorized"

**Symptoms**:
- API returns 401 Unauthorized
- Error message: "Access denied due to invalid subscription key"

**Causes**:
- Invalid or expired API key
- API key not configured correctly
- Wrong Azure resource

**Solutions**:

1. **Verify API key in Azure Portal**:
   - Go to [Azure Portal](https://portal.azure.com)
   - Navigate to your Content Safety resource
   - Check "Keys and Endpoint" section
   - Copy the correct key

2. **Check environment configuration**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

3. **Verify `.env` file**:
   ```env
   AZURE_CONTENT_SAFETY_API_KEY=your-actual-api-key-here
   ```

4. **Test with Artisan command**:
   ```bash
   php artisan azure-moderator:test-image https://via.placeholder.com/150
   ```

---

### Endpoint Configuration

#### Issue: "Could not resolve host" or "Connection refused"

**Symptoms**:
- Network errors
- Cannot connect to Azure API
- Timeout errors

**Causes**:
- Invalid endpoint URL
- Network connectivity issues
- Firewall blocking requests

**Solutions**:

1. **Verify endpoint URL format**:
   ```env
   # Correct format
   AZURE_CONTENT_SAFETY_ENDPOINT=https://your-resource-name.cognitiveservices.azure.com
   
   # Wrong - missing https://
   AZURE_CONTENT_SAFETY_ENDPOINT=your-resource-name.cognitiveservices.azure.com
   
   # Wrong - extra path
   AZURE_CONTENT_SAFETY_ENDPOINT=https://your-resource-name.cognitiveservices.azure.com/contentsafety
   ```

2. **Test network connectivity**:
   ```bash
   curl -I https://your-resource-name.cognitiveservices.azure.com
   ```

3. **Check firewall settings**:
   - Ensure outbound HTTPS (port 443) is allowed
   - Check corporate firewall rules
   - Verify Azure service is accessible from your region

---

### Rate Limiting

#### Issue: "Too Many Requests" (429 Error)

**Symptoms**:
- API returns 429 status code
- Error message: "Rate limit exceeded"
- Requests fail after working initially

**Causes**:
- Exceeded Azure API rate limits
- Too many concurrent requests
- Insufficient quota for pricing tier

**Solutions**:

1. **Check Azure pricing tier**:
   - Free tier: Limited requests per minute
   - Paid tiers: Higher limits

2. **Implement request throttling**:
   ```php
   use Illuminate\Support\Facades\RateLimiter;
   
   RateLimiter::attempt(
       'azure-moderation',
       $perMinute = 10,
       function() use ($content) {
           return AzureModerator::moderate($content, 4.0);
       }
   );
   ```

3. **Use queues for background processing**:
   ```php
   dispatch(new ModerateContentJob($content))->onQueue('moderation');
   ```

4. **Wait and retry**:
   - The package automatically retries with exponential backoff
   - Wait a few minutes if hitting limits frequently

---

### Validation Issues

#### Issue: SafeImage validation always fails

**Symptoms**:
- All image uploads fail validation
- Error: "The image failed safety validation"

**Causes**:
- API configuration issues
- Strict mode enabled with API errors
- Invalid image format

**Solutions**:

1. **Check graceful degradation setting**:
   ```env
   # Allow uploads when API is unavailable (recommended for production)
   AZURE_MODERATOR_FAIL_ON_ERROR=false
   
   # Strict mode - fail when API is unavailable
   AZURE_MODERATOR_FAIL_ON_ERROR=true
   ```

2. **Verify API credentials are working**:
   ```bash
   php artisan azure-moderator:test-image https://via.placeholder.com/150
   ```

3. **Check image format**:
   ```php
   // Supported formats: JPEG, PNG, GIF, BMP, TIFF
   $request->validate([
       'image' => ['required', 'image', 'mimes:jpeg,png,gif,bmp,tiff', new SafeImage()]
   ]);
   ```

---

### Content Moderation

#### Issue: Safe content is being flagged

**Symptoms**:
- Obviously safe content returns "flagged" status
- False positives

**Causes**:
- Severity threshold too low
- Rating threshold too high
- Azure AI analysis sensitivity

**Solutions**:

1. **Adjust severity threshold**:
   ```env
   # Default: 3 (medium severity)
   # Lower = more strict, Higher = more lenient
   AZURE_CONTENT_SAFETY_HIGH_SEVERITY_THRESHOLD=4
   ```

2. **Adjust rating threshold**:
   ```env
   # Default: 2
   # Content with rating below this is flagged
   AZURE_CONTENT_SAFETY_LOW_RATING_THRESHOLD=1
   ```

3. **Review flagged content**:
   ```php
   $result = AzureModerator::moderate($content, $rating);
   
   if ($result['status'] === 'flagged') {
       Log::info('Content flagged', [
           'content' => $content,
           'reason' => $result['reason'],
           'rating' => $rating,
       ]);
   }
   ```

---

### Performance Issues

#### Issue: Slow moderation requests

**Symptoms**:
- Requests take > 5 seconds
- Timeouts
- Poor user experience

**Causes**:
- Large content size
- Network latency
- Azure service load

**Solutions**:

1. **Optimize content size**:
   ```php
   // For images - resize before moderation
   $image = Image::make($file)->resize(1024, 1024);
   
   // For text - limit length
   $text = Str::limit($content, 5000);
   ```

2. **Use background jobs**:
   ```php
   // Don't block user requests
   dispatch(new ModerateContentJob($content));
   ```

3. **Implement caching**:
   ```php
   $cacheKey = 'mod_' . md5($content);
   $result = Cache::remember($cacheKey, 3600, function() use ($content) {
       return AzureModerator::moderate($content, 4.0);
   });
   ```

4. **Check Azure region**:
   - Use Azure region closest to your application
   - Consider multi-region deployment

---

### Integration Test Issues

#### Issue: Integration tests are skipped

**Symptoms**:
- `composer test:integration` shows 0 tests
- All tests marked as skipped

**Causes**:
- Azure credentials not configured
- `.env.integration` file missing

**Solutions**:

1. **Create `.env.integration` file**:
   ```bash
   cp .env.integration.example .env.integration
   ```

2. **Add Azure credentials**:
   ```env
   AZURE_CONTENT_SAFETY_ENDPOINT=https://your-resource.cognitiveservices.azure.com
   AZURE_CONTENT_SAFETY_API_KEY=your-api-key
   ```

3. **Verify credentials are loaded**:
   ```bash
   vendor/bin/pest tests/Integration/TextModerationIntegrationTest.php --verbose
   ```

---

## Error Codes

### Azure Content Safety API Error Codes

| Code | Meaning | Solution |
|------|---------|----------|
| 400 | Bad Request | Check request payload format |
| 401 | Unauthorized | Verify API key |
| 403 | Forbidden | Check resource permissions |
| 404 | Not Found | Verify endpoint URL |
| 429 | Too Many Requests | Implement rate limiting |
| 500 | Internal Server Error | Retry request, contact Azure support |
| 503 | Service Unavailable | Azure service issue, retry later |

### Package Error Messages

| Message | Cause | Solution |
|---------|-------|----------|
| "Image cannot be empty" | Empty image parameter | Provide valid image URL or base64 data |
| "Invalid image URL provided" | Malformed URL | Check URL format |
| "Base64 image data exceeds maximum size" | Image > 4MB | Resize image before encoding |
| "Encoding must be either 'url' or 'base64'" | Invalid encoding parameter | Use 'url' or 'base64' |

---

## Debugging Tips

### Enable Detailed Logging

Add logging to track moderation requests:

```php
use Illuminate\Support\Facades\Log;

$result = AzureModerator::moderate($content, $rating);

Log::debug('Moderation result', [
    'content' => Str::limit($content, 100),
    'rating' => $rating,
    'result' => $result,
]);
```

### Test with Artisan Command

Use the built-in command to test image moderation:

```bash
# Test with URL
php artisan azure-moderator:test-image https://via.placeholder.com/150

# Test with specific categories
php artisan azure-moderator:test-image https://via.placeholder.com/150 --categories=Sexual,Violence
```

### Check Configuration

Verify all configuration values are loaded:

```php
dd([
    'endpoint' => config('azure-moderator.endpoint'),
    'api_key' => config('azure-moderator.api_key') ? 'Set' : 'Not set',
    'low_rating_threshold' => config('azure-moderator.low_rating_threshold'),
    'high_severity_threshold' => config('azure-moderator.high_severity_threshold'),
    'fail_on_api_error' => config('azure-moderator.fail_on_api_error'),
]);
```

### Test API Connectivity

Test direct API access:

```bash
curl -X POST "https://your-resource.cognitiveservices.azure.com/contentsafety/text:analyze?api-version=2023-10-01" \
  -H "Ocp-Apim-Subscription-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"text": "Test message"}'
```

---

## Getting Help

### Before Asking for Help

1. Check this troubleshooting guide
2. Review [Integration Testing Guide](INTEGRATION_TESTING.md)
3. Check [Azure Content Safety documentation](https://learn.microsoft.com/en-us/azure/ai-services/content-safety/)
4. Search existing GitHub issues

### When Reporting Issues

Include:
- PHP version
- Laravel version
- Package version
- Error message (full stack trace)
- Configuration (without sensitive data)
- Steps to reproduce

### Resources

- **GitHub Issues**: [gowelle/azure-moderator/issues](https://github.com/gowelle/azure-moderator/issues)
- **Email**: gowelle.john@icloud.com
- **Azure Support**: [Azure Portal](https://portal.azure.com)
- **Documentation**: [Package README](../README.md)
