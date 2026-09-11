<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Data;

/** Minimal, application-declared origin information. Not a standardized watermark. */
final class AiProvenance
{
    public static function hash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Never export private generation IDs, reviewer identities or provider evidence. */
    public static function publicFields(?array $origins): array
    {
        $public = [];
        foreach ($origins ?? [] as $field => $origin) {
            if (in_array($field, ['title', 'description', 'schema_jsonld'], true)
                && is_array($origin) && ($origin['origin'] ?? null) === 'ai') {
                $public[$field] = ['origin' => 'ai', 'edited' => (bool) ($origin['edited'] ?? false)];
            }
        }

        return $public;
    }
}
