<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Contracts;

/**
 * A model that can render itself as clean markdown for AI crawlers.
 *
 * Implement this (or just add the method — it's detected by name) to control
 * exactly what the markdown-for-bots content negotiation serves for a page,
 * instead of the built-in title + description + getContentForSEO() fallback.
 * Return null to opt a record out of markdown serving.
 *
 * @see \Rankbeam\Seo\Http\Middleware\ServeMarkdownToBots
 * @see \Rankbeam\Seo\Services\Markdown\MarkdownRegistry
 */
interface ProvidesSeoMarkdown
{
    public function toSeoMarkdown(): ?string;
}
