<?php

namespace Gowelle\AzureModerator\Enums;

/**
 * Enumeration of Azure Content Safety categories
 *
 * This enum defines the content categories that can be analyzed
 * by the Azure Content Safety API.
 *
 * @see https://learn.microsoft.com/en-us/azure/ai-services/content-safety/concepts/harm-categories
 */
enum ContentCategory: string 
{
    /** Content expressing hate or discrimination */
    case HATE = 'Hate';
    
    /** Content related to self-harm or suicide */
    case SELF_HARM = 'SelfHarm';
    
    /** Sexual or adult content */
    case SEXUAL = 'Sexual';
    
    /** Violent or graphic content */
    case VIOLENCE = 'Violence';

    /**
     * Get array of default categories for moderation
     *
     * @return array<string>
     */
    public static function defaultCategories(): array
    {
        return [
            self::HATE->value,
            self::SELF_HARM->value,
            self::SEXUAL->value,
            self::VIOLENCE->value,
        ];
    }
}