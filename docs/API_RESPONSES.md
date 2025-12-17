# API Response Examples

This document provides example API requests and responses for the Azure Content Safety Laravel package.

> [!NOTE]
> As of v2.0.0, all moderation methods return strongly-typed DTO objects (`ModerationResult`) instead of arrays. The examples below show both the DTO usage and the underlying data structure.

## Text Moderation

### Example 1: Approved Content

**Request**:
```php
use Gowelle\AzureModerator\Facades\AzureModerator;

$result = AzureModerator::moderate('This is a great product! I highly recommend it.', 5.0);
```

**Response** (`ModerationResult` DTO):
```php
$result->isApproved();  // true
$result->status;        // ModerationStatus::APPROVED
$result->reason;        // null
```

**As Array** (via `$result->toArray()`):
```php
[
    'status' => 'approved',
    'reason' => null,
    'trackingId' => 'abc123...',
    'categoriesAnalysis' => [...],
    'blocklistMatches' => null,
]
```

---

### Example 2: Flagged Due to Low Rating

**Request**:
```php
$result = AzureModerator::moderate('This product is okay.', 1.5);
```

**Response** (`ModerationResult` DTO):
```php
$result->isApproved();  // false
$result->isFlagged();   // true
$result->status;        // ModerationStatus::FLAGGED
$result->reason;        // 'low_rating'
```

---

### Example 3: Flagged Due to High Severity Content

**Request**:
```php
$result = AzureModerator::moderate('Content with potentially harmful language.', 4.0);
```

**Response** (`ModerationResult` DTO if Azure detects high severity):
```php
$result->isApproved();  // false
$result->reason;        // 'Hate' or 'Violence', 'Sexual', 'SelfHarm'

// Access detailed category scores
foreach ($result->categoriesAnalysis as $analysis) {
    echo $analysis->category->value; // 'Hate', 'Violence', etc.
    echo $analysis->severity;        // 0-7
}
```

---

### Example 4: Moderation with Specific Categories

**Request**:
```php
use Gowelle\AzureModerator\Enums\ContentCategory;

$result = AzureModerator::moderate(
    'Test message',
    4.0,
    [ContentCategory::HATE->value, ContentCategory::VIOLENCE->value]
);
```

**Response** (`ModerationResult` DTO):
```php
$result->isApproved();        // true or false
$result->reason;              // null or category name if flagged
$result->categoriesAnalysis;  // Array of CategoryAnalysis DTOs
```

---

## Image Moderation

### Example 1: Safe Image (URL)

**Request**:
```php
$result = AzureModerator::moderateImage('https://example.com/safe-image.jpg');
```

**Response** (`ModerationResult` DTO):
```php
$result->isApproved();  // true
$result->reason;        // null

// Access category analysis
foreach ($result->categoriesAnalysis as $analysis) {
    echo $analysis->category->value; // 'Hate', 'SelfHarm', 'Sexual', 'Violence'
    echo $analysis->severity;        // 0, 0, 0, 1
}
```

---

### Example 2: Flagged Image

**Request**:
```php
$result = AzureModerator::moderateImage('https://example.com/flagged-image.jpg');
```

**Response** (`ModerationResult` DTO):
```php
$result->isApproved();  // false
$result->reason;        // 'Sexual, Violence' (multiple categories)

// Get specific category severity using helper method
use Gowelle\AzureModerator\Enums\ContentCategory;
$result->getSeverity(ContentCategory::SEXUAL);   // 6
$result->getSeverity(ContentCategory::VIOLENCE); // 4
```

---

### Example 3: Base64 Image Moderation

**Request**:
```php
$imageData = file_get_contents('path/to/image.jpg');
$base64Image = base64_encode($imageData);

$result = AzureModerator::moderateImage($base64Image, encoding: 'base64');
```

**Response**:
```php
[
    'status' => 'approved',
    'reason' => null,
    'scores' => [
        ['category' => 'Hate', 'severity' => 0],
        ['category' => 'SelfHarm', 'severity' => 0],
        ['category' => 'Sexual', 'severity' => 1],
        ['category' => 'Violence', 'severity' => 0],
    ],
]
```

---

### Example 4: Image with Specific Categories

**Request**:
```php
$result = AzureModerator::moderateImage(
    'https://example.com/image.jpg',
    categories: [ContentCategory::SEXUAL->value]
);
```

**Response**:
```php
[
    'status' => 'approved',
    'reason' => null,
    'scores' => [
        ['category' => 'Sexual', 'severity' => 2],
    ],
]
```

---

## Laravel Validation

### Example 1: SafeImage Validation Rule

**Request**:
```php
use Gowelle\AzureModerator\Rules\SafeImage;
use Illuminate\Http\Request;

$request->validate([
    'avatar' => ['required', 'image', 'max:2048', new SafeImage()],
]);
```

**Success Response**:
```php
// Validation passes, no exception thrown
// Image is safe and can be processed
```

**Failure Response**:
```php
// ValidationException thrown with message:
"The avatar contains inappropriate content and cannot be uploaded."
```

---

### Example 2: SafeImage with Custom Categories

**Request**:
```php
$request->validate([
    'profile_picture' => [
        'required',
        'image',
        new SafeImage([
            ContentCategory::SEXUAL->value,
            ContentCategory::VIOLENCE->value,
        ])
    ],
]);
```

**Success/Failure**: Same as Example 1

---

## Error Responses

### Example 1: Graceful Degradation (Default)

**Scenario**: Azure API is unavailable

**Request**:
```php
// API is down or credentials are invalid
$result = AzureModerator::moderate('Test content', 4.0);
```

**Response**:
```php
[
    'status' => 'approved',  // Gracefully approves when API fails
    'reason' => null,
]
```

---

### Example 2: Strict Mode

**Configuration**:
```env
AZURE_MODERATOR_FAIL_ON_ERROR=true
```

**Scenario**: Azure API is unavailable

**Request**:
```php
use Gowelle\AzureModerator\Rules\SafeImage;

$request->validate([
    'image' => [new SafeImage()],
]);
```

**Response**:
```php
// ValidationException thrown with message:
"Unable to validate image safety. Please try again."
```

---

### Example 3: Invalid Input

**Request**:
```php
// Empty image
$result = AzureModerator::moderateImage('');
```

**Response**:
```php
// InvalidArgumentException thrown with message:
"Image cannot be empty"
```

---

**Request**:
```php
// Invalid URL
$result = AzureModerator::moderateImage('not-a-valid-url', encoding: 'url');
```

**Response**:
```php
// InvalidArgumentException thrown with message:
"Invalid image URL provided"
```

---

**Request**:
```php
// Oversized base64 image
$largeImage = str_repeat('a', 4194305);  // > 4MB
$result = AzureModerator::moderateImage($largeImage, encoding: 'base64');
```

**Response**:
```php
// InvalidArgumentException thrown with message:
"Base64 image data exceeds maximum size of 4MB (approximately 3MB original image size)"
```

---

## Azure API Raw Responses

### Text Analysis API Response

**Azure API Endpoint**: `POST /contentsafety/text:analyze`

**Request Payload**:
```json
{
  "text": "This is a test message",
  "categories": ["Hate", "SelfHarm", "Sexual", "Violence"]
}
```

**Success Response** (200 OK):
```json
{
  "categoriesAnalysis": [
    {
      "category": "Hate",
      "severity": 0
    },
    {
      "category": "SelfHarm",
      "severity": 0
    },
    {
      "category": "Sexual",
      "severity": 2
    },
    {
      "category": "Violence",
      "severity": 0
    }
  ]
}
```

**Severity Levels**:
- `0-2`: Low severity (safe)
- `3-5`: Medium severity
- `6-7`: High severity (flagged by default)

---

### Image Analysis API Response

**Azure API Endpoint**: `POST /contentsafety/image:analyze`

**Request Payload** (URL):
```json
{
  "image": {
    "url": "https://example.com/image.jpg"
  },
  "categories": ["Hate", "SelfHarm", "Sexual", "Violence"]
}
```

**Request Payload** (Base64):
```json
{
  "image": {
    "content": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB..."
  },
  "categories": ["Sexual", "Violence"]
}
```

**Success Response** (200 OK):
```json
{
  "categoriesAnalysis": [
    {
      "category": "Hate",
      "severity": 0
    },
    {
      "category": "SelfHarm",
      "severity": 0
    },
    {
      "category": "Sexual",
      "severity": 4
    },
    {
      "category": "Violence",
      "severity": 2
    }
  ]
}
```

---

### Error Responses from Azure API

**400 Bad Request**:
```json
{
  "error": {
    "code": "InvalidRequest",
    "message": "The request is invalid",
    "details": [
      {
        "code": "InvalidParameter",
        "message": "The 'text' parameter is required"
      }
    ]
  }
}
```

**401 Unauthorized**:
```json
{
  "error": {
    "code": "Unauthorized",
    "message": "Access denied due to invalid subscription key"
  }
}
```

**429 Too Many Requests**:
```json
{
  "error": {
    "code": "TooManyRequests",
    "message": "Rate limit is exceeded. Try again later."
  }
}
```

**500 Internal Server Error**:
```json
{
  "error": {
    "code": "InternalServerError",
    "message": "An internal error occurred. Please try again later."
  }
}
```

---

## Edge Cases

### Example 1: Empty Text

**Request**:
```php
$result = AzureModerator::moderate('', 4.0);
```

**Response**:
```php
[
    'status' => 'approved',
    'reason' => null,
]
```

---

### Example 2: Very Long Text

**Request**:
```php
$longText = str_repeat('This is a test sentence. ', 1000);
$result = AzureModerator::moderate($longText, 4.0);
```

**Response**:
```php
[
    'status' => 'approved',  // or 'flagged' based on content
    'reason' => null,
]
```

---

### Example 3: Unicode Characters

**Request**:
```php
$result = AzureModerator::moderate('Hello 你好 مرحبا 🌍', 4.0);
```

**Response**:
```php
[
    'status' => 'approved',
    'reason' => null,
]
```

---

### Example 4: Multiple Flagged Categories

**Request**:
```php
$result = AzureModerator::moderateImage('https://example.com/multi-flag.jpg');
```

**Response**:
```php
[
    'status' => 'flagged',
    'reason' => 'Hate, Sexual, Violence',  // All categories above threshold
    'scores' => [
        ['category' => 'Hate', 'severity' => 5],
        ['category' => 'SelfHarm', 'severity' => 0],
        ['category' => 'Sexual', 'severity' => 6],
        ['category' => 'Violence', 'severity' => 7],
    ],
]
```

---

## Additional Resources

- [Integration Testing Guide](INTEGRATION_TESTING.md)
- [Performance Testing Guide](PERFORMANCE_TESTING.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Azure Content Safety API Documentation](https://learn.microsoft.com/en-us/azure/ai-services/content-safety/)
