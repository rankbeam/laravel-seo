/**
 * SEO Hook for React + Inertia
 *
 * Returns Helmet-compatible props object for use with react-helmet-async.
 *
 * @package fibonoir/laravel-seo
 */

import { useMemo } from 'react';
import type { SEOData, HelmetProps, OpenGraphData, TwitterCardData } from '../types/seo';

/**
 * Options for the useSEO hook
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
 * Hook to generate Helmet-compatible props from SEO data
 *
 * @example
 * ```tsx
 * import { Helmet } from 'react-helmet-async';
 * import { useSEO } from '@fibonoir/laravel-seo/react';
 *
 * function Page({ seoData }) {
 *   const helmetProps = useSEO(seoData, {
 *     siteName: 'My Site',
 *     titleTemplate: '%s | My Site',
 *   });
 *
 *   return (
 *     <>
 *       <Helmet {...helmetProps} />
 *       <main>...</main>
 *     </>
 *   );
 * }
 * ```
 */
export function useSEO(
  data: SEOData | null | undefined,
  options: UseSEOOptions = {}
): HelmetProps {
  const {
    siteName = '',
    titleTemplate = '%s',
    twitterSite = '',
    locale = 'en_US',
  } = options;

  return useMemo(() => {
    if (!data) {
      return {};
    }

    const meta: HelmetProps['meta'] = [];
    const link: HelmetProps['link'] = [];
    const script: HelmetProps['script'] = [];

    // Basic meta description
    if (data.description) {
      meta.push({ name: 'description', content: data.description });
    }

    // Robots directive
    const robots = buildRobotsContent(data.robots);
    if (robots) {
      meta.push({ name: 'robots', content: robots });
    }

    // Open Graph tags
    const ogMeta = buildOpenGraphMeta(data, siteName, locale);
    meta.push(...ogMeta);

    // Twitter Card tags
    const twitterMeta = buildTwitterMeta(data, twitterSite);
    meta.push(...twitterMeta);

    // Canonical URL
    if (data.canonical) {
      link.push({ rel: 'canonical', href: data.canonical });
    }

    // JSON-LD Schema
    const jsonLd = buildJsonLd(data);
    if (jsonLd) {
      script.push({
        type: 'application/ld+json',
        innerHTML: JSON.stringify(jsonLd),
      });
    }

    return {
      title: data.title,
      titleTemplate: data.title ? titleTemplate : undefined,
      meta,
      link,
      script,
    };
  }, [data, siteName, titleTemplate, twitterSite, locale]);
}

/**
 * Build robots meta content from directive
 */
function buildRobotsContent(
  robots: SEOData['robots']
): string | undefined {
  if (!robots) return undefined;

  if (typeof robots === 'string') {
    return robots;
  }

  const directives: string[] = [];
  directives.push(robots.index ? 'index' : 'noindex');
  directives.push(robots.follow ? 'follow' : 'nofollow');

  if (robots.additional) {
    directives.push(...robots.additional);
  }

  return directives.join(', ');
}

/**
 * Build Open Graph meta tags
 */
function buildOpenGraphMeta(
  data: SEOData,
  siteName: string,
  locale: string
): Array<{ property: string; content: string }> {
  const og: OpenGraphData = data.openGraph || {};
  const meta: Array<{ property: string; content: string }> = [];

  // Support both nested and flat properties
  const ogTitle = og.title || data.ogTitle || data.title;
  const ogDescription = og.description || data.ogDescription || data.description;
  const ogImage = og.image || data.ogImage;
  const ogType = og.type || data.ogType || 'website';
  const ogSiteName = og.siteName || siteName;
  const ogLocale = og.locale || locale;

  if (ogTitle) meta.push({ property: 'og:title', content: ogTitle });
  if (ogDescription) meta.push({ property: 'og:description', content: ogDescription });
  if (ogImage) meta.push({ property: 'og:image', content: ogImage });
  if (ogType) meta.push({ property: 'og:type', content: ogType });
  if (ogSiteName) meta.push({ property: 'og:site_name', content: ogSiteName });
  if (ogLocale) meta.push({ property: 'og:locale', content: ogLocale });
  if (data.canonical) meta.push({ property: 'og:url', content: data.canonical });

  return meta;
}

/**
 * Build Twitter Card meta tags
 */
function buildTwitterMeta(
  data: SEOData,
  twitterSite: string
): Array<{ name: string; content: string }> {
  const twitter: TwitterCardData = typeof data.twitterCard === 'object' ? data.twitterCard : {};
  const meta: Array<{ name: string; content: string }> = [];

  // Support both nested and flat properties
  const cardType = twitter.card || data.twitterCardType || 'summary_large_image';
  const twitterTitle = twitter.title || data.twitterTitle || data.ogTitle || data.title;
  const twitterDescription = twitter.description || data.twitterDescription || data.ogDescription || data.description;
  const twitterImage = twitter.image || data.twitterImage || data.ogImage;
  const twitterSiteHandle = twitter.site || twitterSite;
  const twitterCreator = twitter.creator;

  meta.push({ name: 'twitter:card', content: cardType });
  if (twitterTitle) meta.push({ name: 'twitter:title', content: twitterTitle });
  if (twitterDescription) meta.push({ name: 'twitter:description', content: twitterDescription });
  if (twitterImage) meta.push({ name: 'twitter:image', content: twitterImage });
  if (twitterSiteHandle) meta.push({ name: 'twitter:site', content: twitterSiteHandle });
  if (twitterCreator) meta.push({ name: 'twitter:creator', content: twitterCreator });

  return meta;
}

/**
 * Build JSON-LD structured data
 */
function buildJsonLd(data: SEOData): Record<string, unknown> | null {
  // If custom JSON-LD is provided, use it
  if (data.schemaJsonld) {
    if (typeof data.schemaJsonld === 'string') {
      try {
        return JSON.parse(data.schemaJsonld);
      } catch {
        console.warn('[useSEO] Invalid JSON-LD schema:', data.schemaJsonld);
        return null;
      }
    }
    return data.schemaJsonld;
  }

  // If schema type is provided, build basic schema
  if (data.schemaType) {
    const schema: Record<string, unknown> = {
      '@context': 'https://schema.org',
      '@type': data.schemaType,
    };

    if (data.title) schema.name = data.title;
    if (data.description) schema.description = data.description;
    if (data.canonical) schema.url = data.canonical;
    if (data.ogImage || data.openGraph?.image) {
      schema.image = data.ogImage || data.openGraph?.image;
    }

    return schema;
  }

  return null;
}

export default useSEO;
