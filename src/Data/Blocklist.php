<?php

namespace Gowelle\AzureModerator\Data;

/**
 * Data object for Blocklist definition
 */
class Blocklist
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null
    ) {}
}
