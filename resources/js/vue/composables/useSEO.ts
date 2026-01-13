/**
 * SEO Composable for Vue + Inertia
 *
 * Uses @unhead/vue to manage meta tags in the document head.
 *
 * @package fibonoir/laravel-seo
 */

import { useHead } from "@unhead/vue";
import { computed, type MaybeRefOrGetter, toValue } from "vue";
import type { SEOData, OpenGraphData, TwitterCardData } from "../types/seo";

/**
 * Options for the useSEO composable
 */
export interface UseSEOOptions {
    /** Site name for OG tags */
    siteName?: string;
    /** Default title template (use %s for page title) */
    titleTemplate?: string;
    /** Twitter site handle */
    twitterSite?: string;
    /** Default locale */
    locale?: string;
}

/**
 * Composable to manage SEO meta tags using @unhead/vue
 *
 * @example
 * ```vue
 * <script setup lang="ts">
 * import { useSEO } from '@fibonoir/laravel-seo/vue';
 *
 * const seoData = {
 *   title: 'My Page Title',
 *   description: 'My page description',
 *   canonical: 'https://example.com/my-page',
 *   openGraph: {
 *     image: 'https://example.com/og-image.jpg',
 *   },
 * };
 *
 * useSEO(seoData, {
 *   siteName: 'My Site',
 *   titleTemplate: '%s | My Site',
 * });
 * </script>
 * ```
 */
export function useSEO(
    data: MaybeRefOrGetter<SEOData>,
    options: UseSEOOptions = {}
): void {
    const {
        siteName = "",
        titleTemplate = "%s",
        twitterSite = "",
        locale = "en_US",
    } = options;

    useHead(
        computed(() => {
            const seoData = toValue(data);

            // Build title
            const title = seoData.title || "";

            // Build robots directive
            const robots = buildRobotsContent(seoData.robots);

            // Build Open Graph data
            const og = buildOpenGraphMeta(seoData, siteName, locale);

            // Build Twitter Card data
            const twitter = buildTwitterMeta(seoData, twitterSite);

            // Build JSON-LD schema
            const jsonLd = buildJsonLd(seoData);

            return {
                title,
                titleTemplate: title ? titleTemplate : undefined,
                meta: [
                    // Basic meta
                    seoData.description
                        ? { name: "description", content: seoData.description }
                        : undefined,
                    robots ? { name: "robots", content: robots } : undefined,

                    // Open Graph
                    ...og,

                    // Twitter Card
                    ...twitter,
                ].filter(Boolean),
                link: [
                    // Canonical URL
                    seoData.canonical
                        ? { rel: "canonical", href: seoData.canonical }
                        : undefined,
                ].filter(Boolean),
                script: jsonLd
                    ? [
                          {
                              type: "application/ld+json",
                              innerHTML: JSON.stringify(jsonLd),
                          },
                      ]
                    : [],
            };
        })
    );
}

/**
 * Build robots meta content from directive
 */
function buildRobotsContent(robots: SEOData["robots"]): string | undefined {
    if (!robots) return undefined;

    if (typeof robots === "string") {
        return robots;
    }

    const directives: string[] = [];
    directives.push(robots.index ? "index" : "noindex");
    directives.push(robots.follow ? "follow" : "nofollow");

    if (robots.additional) {
        directives.push(...robots.additional);
    }

    return directives.join(", ");
}

/**
 * Build Open Graph meta tags
 */
function buildOpenGraphMeta(
    seoData: SEOData,
    siteName: string,
    locale: string
): Array<{ property: string; content: string } | undefined> {
    const og: OpenGraphData = seoData.openGraph || {};

    // Support both nested and flat properties
    const ogTitle = og.title || seoData.ogTitle || seoData.title;
    const ogDescription =
        og.description || seoData.ogDescription || seoData.description;
    const ogImage = og.image || seoData.ogImage;
    const ogType = og.type || seoData.ogType || "website";
    const ogSiteName = og.siteName || siteName;
    const ogLocale = og.locale || locale;

    return [
        ogTitle ? { property: "og:title", content: ogTitle } : undefined,
        ogDescription
            ? { property: "og:description", content: ogDescription }
            : undefined,
        ogImage ? { property: "og:image", content: ogImage } : undefined,
        ogType ? { property: "og:type", content: ogType } : undefined,
        ogSiteName
            ? { property: "og:site_name", content: ogSiteName }
            : undefined,
        ogLocale ? { property: "og:locale", content: ogLocale } : undefined,
        seoData.canonical
            ? { property: "og:url", content: seoData.canonical }
            : undefined,
    ];
}

/**
 * Build Twitter Card meta tags
 */
function buildTwitterMeta(
    seoData: SEOData,
    twitterSite: string
): Array<{ name: string; content: string } | undefined> {
    const twitter: TwitterCardData = seoData.twitterCard || {};

    // Support both nested and flat properties
    const cardType =
        (typeof twitter === "object" ? twitter.card : undefined) ||
        seoData.twitterCardType ||
        "summary_large_image";
    const twitterTitle =
        twitter.title ||
        seoData.twitterTitle ||
        seoData.ogTitle ||
        seoData.title;
    const twitterDescription =
        twitter.description ||
        seoData.twitterDescription ||
        seoData.ogDescription ||
        seoData.description;
    const twitterImage =
        twitter.image || seoData.twitterImage || seoData.ogImage;
    const twitterSiteHandle = twitter.site || twitterSite;
    const twitterCreator = twitter.creator;

    return [
        { name: "twitter:card", content: cardType },
        twitterTitle
            ? { name: "twitter:title", content: twitterTitle }
            : undefined,
        twitterDescription
            ? { name: "twitter:description", content: twitterDescription }
            : undefined,
        twitterImage
            ? { name: "twitter:image", content: twitterImage }
            : undefined,
        twitterSiteHandle
            ? { name: "twitter:site", content: twitterSiteHandle }
            : undefined,
        twitterCreator
            ? { name: "twitter:creator", content: twitterCreator }
            : undefined,
    ];
}

/**
 * Build JSON-LD structured data
 */
function buildJsonLd(seoData: SEOData): Record<string, unknown> | undefined {
    // If custom JSON-LD is provided, use it
    if (seoData.schemaJsonld) {
        if (typeof seoData.schemaJsonld === "string") {
            try {
                return JSON.parse(seoData.schemaJsonld);
            } catch {
                console.warn(
                    "[useSEO] Invalid JSON-LD schema:",
                    seoData.schemaJsonld
                );
                return undefined;
            }
        }
        return seoData.schemaJsonld;
    }

    // If schema type is provided, build basic schema
    if (seoData.schemaType) {
        const schema: Record<string, unknown> = {
            "@context": "https://schema.org",
            "@type": seoData.schemaType,
        };

        if (seoData.title) {
            schema.name = seoData.title;
        }
        if (seoData.description) {
            schema.description = seoData.description;
        }
        if (seoData.canonical) {
            schema.url = seoData.canonical;
        }
        if (seoData.ogImage || seoData.openGraph?.image) {
            schema.image = seoData.ogImage || seoData.openGraph?.image;
        }

        return schema;
    }

    return undefined;
}

export default useSEO;
