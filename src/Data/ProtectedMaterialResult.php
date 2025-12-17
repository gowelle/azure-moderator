<?php

namespace Gowelle\AzureModerator\Data;

/**
 * Data object for Protected Material Detection Result
 */
class ProtectedMaterialResult
{
    /**
     * @param bool $detected Whether protected material was detected
     * @param array $details Raw analysis details from API
     */
    public function __construct(
        public readonly bool $detected,
        public readonly array $details = []
    ) {}
}
