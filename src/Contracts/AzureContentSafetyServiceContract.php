<?php

namespace Gowelle\AzureModerator\Contracts;

interface AzureContentSafetyServiceContract
{
    /**
     * Moderate the given text based on the rating.
     * 
     * @param string $text The text to be moderated.
     * @param float $rating The rating of the text.
     */
    public function moderate(string $text, float $rating): array;
}