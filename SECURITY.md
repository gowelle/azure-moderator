# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:               |

## Security Best Practices

When using this package, follow these security best practices:

### API Credentials
- **Never commit API keys to version control.** Use environment variables via `.env` file
- **Keep `.env` out of repositories** - ensure it's in your `.gitignore`
- **Rotate API keys regularly** - implement key rotation procedures in Azure Portal
- **Use environment-specific keys** - separate keys for development, staging, and production
- **Monitor API usage** - regularly check Azure Portal for unusual patterns

```env
# Correct: Use environment variables
AZURE_CONTENT_SAFETY_ENDPOINT=${AZURE_ENDPOINT}
AZURE_CONTENT_SAFETY_API_KEY=${AZURE_KEY}

# Incorrect: Never hardcode credentials
# AZURE_CONTENT_SAFETY_API_KEY=sk_live_xxxxxxxxxxxx
```

### Input Validation
- The package validates inputs before sending to Azure API
- **Text moderation:** Rating parameter (0-5) is validated
- **Image moderation:** URLs and base64 data are validated before processing
- **File uploads:** SafeImage rule validates files are actual uploaded files
- Never bypass these validations; they prevent API abuse

### Base64 Image Size Limits
- Maximum 4MB of encoded base64 data
- Approximately 3MB of original image size (33% overhead)
- Exceeding limits will cause API errors
- The SafeImage rule respects `fail_on_api_error` configuration for handling oversized uploads

### API Error Handling
The `fail_on_api_error` configuration controls security vs. user experience trade-offs:

- **Default (false):** Graceful degradation - allows content through during API outages
  - Use for: Public-facing applications where user experience is critical
  - Trade-off: Some moderation gaps during Azure API downtime

- **Strict (true):** Fail-closed - rejects content when API is unavailable
  - Use for: High-security applications requiring strict enforcement
  - Trade-off: Users unable to upload during API outages

### Rate Limiting
- Azure Content Safety API has rate limits (varies by subscription tier)
- The package includes automatic retry logic with exponential backoff for:
  - 429 (Too Many Requests)
  - 500 (Internal Server Error)
  - 503 (Service Unavailable)
- Up to 3 retry attempts per request
- If rate limited, consider:
  - Upgrading Azure subscription tier
  - Implementing request queuing on client-side
  - Caching moderation results for duplicate content

### Logging and Monitoring
- All API interactions are logged via Laravel logging
- Sensitive data (API keys) are never logged
- Monitor logs for:
  - Repeated API failures
  - Unusual content patterns
  - Rate limit warnings (429 responses)
- Example log entry:
  ```
  [2025-11-15 10:30:45] production.WARNING: Image moderation validation failed
  {
    "file": "SafeImage.php",
    "message": "API Error during validation",
    "status_code": 500
  }
  ```

### Data Privacy
- This package sends content (text and images) to Azure Content Safety API
- Review Azure's data retention policies: https://learn.microsoft.com/en-us/azure/ai-services/content-safety/overview
- For GDPR/privacy compliance, ensure:
  - Users are informed content is analyzed by Azure
  - Compliance with your region's data protection laws
  - Consideration of content sensitivity before submission

## Reporting a Vulnerability

We take security issues seriously. Thank you for helping us maintain the security of our package.

**Please do NOT report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to [security@gowelle.com](mailto:security@gowelle.com).

You should receive a response within 48 hours. If for some reason you do not, please follow up via email to ensure we received your original message.

Please include the following information in your report:

- Type of issue (e.g. buffer overflow, SQL injection, cross-site scripting, etc.)
- Full paths of source file(s) related to the manifestation of the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

## Disclosure Policy

When we receive a security bug report, we will:

1. Confirm the problem and determine the affected versions
2. Audit code to find any potential similar problems
3. Prepare fixes for all still-maintained versions
4. Release new security fix versions as soon as possible

## Comments on this Policy

If you have suggestions on how this process could be improved, please submit a pull request.