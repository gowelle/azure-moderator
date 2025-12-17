<?php

namespace Gowelle\AzureModerator\Data;

/**
 * Data object for blocklist match details
 */
class BlocklistMatch
{
    public function __construct(
        public readonly string $blocklistName,
        public readonly string $matchId,
        public readonly string $matchValue
    ) {}
}
