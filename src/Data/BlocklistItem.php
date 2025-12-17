<?php

namespace Gowelle\AzureModerator\Data;

/**
 * Data object for Blocklist Item
 */
class BlocklistItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $text
    ) {}
}
