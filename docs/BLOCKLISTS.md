# Custom Blocklists Guide

Azure Content Safety for Laravel supports managing custom blocklists to filter specific terms or patterns from your content. This guide explains how to create, manage, and use blocklists in your application.

## Overview

Blocklists allow you to:
- Define custom terms to block (e.g., competitor names, specific slurs)
- Halt moderation immediately when a blocklist term is found
- Manage multiple blocklists

## Configuration

You can configure default blocklists in your `.env` file or `config/azure-moderator.php`:

```env
# Enable or disable blocklists globally
AZURE_MODERATOR_BLOCKLISTS_ENABLED=true

# Comma-separated list of default blocklists to use for all requests
AZURE_MODERATOR_DEFAULT_BLOCKLISTS=global-ban-list,competitors

# Whether to stop analysis immediately when a blocklist term matches
# If true, you won't get full category verification, just the blocklist match
AZURE_MODERATOR_HALT_ON_BLOCKLIST_HIT=false
```

## Managing Blocklists via CLI

This package provides a comprehensive Artisan command to manage blocklists without writing code.

### 1. Create a Blocklist
```bash
php artisan azure-moderator:blocklist create my-list "List of banned terms"
```

### 2. Add Items
Add terms to your blocklist:
```bash
php artisan azure-moderator:blocklist add-item my-list "term1"
php artisan azure-moderator:blocklist add-item my-list "term2"
```

### 3. List Blocklists
See all your defined blocklists:
```bash
php artisan azure-moderator:blocklist list
```

### 4. View Items
See what's in a blocklist:
```bash
php artisan azure-moderator:blocklist list-items my-list
```

### 5. Remove Items
```bash
php artisan azure-moderator:blocklist remove-item my-list "term1"
```

### 6. Delete a Blocklist
```bash
php artisan azure-moderator:blocklist delete my-list
```

## Programmatic Usage

You can also manage blocklists programmatically using the `BlocklistService`.

```php
use Gowelle\AzureModerator\Services\BlocklistService;

$service = app(BlocklistService::class);

// Create
$service->createBlocklist('my-list', 'Description');

// Add items
$service->addBlocklistItems('my-list', ['bad_word_1', 'bad_word_2']);

// List
$lists = $service->listBlocklists();
```

## Using Blocklists in Moderation

You can specify which blocklists to use during moderation.

### Facade Usage

```php
use Gowelle\AzureModerator\Facades\AzureModerator;

$result = AzureModerator::moderate(
    text: 'Some content',
    rating: 0.5,
    blocklistNames: ['my-list', 'global-list'],
    haltOnBlocklistHit: true
);

// Check for blocklist matches using DTO properties
if ($result->blocklistMatches) {
    foreach ($result->blocklistMatches as $match) {
        echo "Matched term: " . $match->matchValue;
        echo "From blocklist: " . $match->blocklistName;
    }
}
```

### Response Failure

If a blocklist term is matched, the result will be flagged:

```php
// The ModerationResult DTO indicates flagged status
$result->isApproved();  // false
$result->isFlagged();   // true
$result->reason;        // 'Hate, blocklist_match' or simply 'blocklist_match'

// Access blocklist matches (array of BlocklistMatch DTOs)
foreach ($result->blocklistMatches as $match) {
    $match->blocklistName;  // 'my-list'
    $match->matchId;        // Azure match ID
    $match->matchValue;     // 'term1'
}
```
