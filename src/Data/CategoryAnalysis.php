<?php

namespace Gowelle\AzureModerator\Data;

use Gowelle\AzureModerator\Enums\ContentCategory;

/**
 * Data object for category analysis score
 */
class CategoryAnalysis
{
    public function __construct(
        public readonly ContentCategory $category,
        public readonly int $severity
    ) {}
}
