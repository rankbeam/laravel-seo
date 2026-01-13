/**
 * SEO Head Component for React
 *
 * Wrapper using react-helmet-async to render all SEO meta tags.
 *
 * @package fibonoir/laravel-seo
 */

import React from 'react';
import { Helmet } from 'react-helmet-async';
import { useSEO, type UseSEOOptions } from '../hooks/useSEO';
import type { SEOData } from '../types/seo';

export interface SEOHeadProps extends SEOData, UseSEOOptions {
  /** Children to render inside Helmet (additional tags) */
  children?: React.ReactNode;
}

/**
 * SEOHead component renders all SEO meta tags using react-helmet-async
 *
 * @example
 * ```tsx
 * import { SEOHead } from '@fibonoir/laravel-seo/react';
 *
 * function Page() {
 *   return (
 *     <>
 *       <SEOHead
 *         title="My Page Title"
 *         description="My page description"
 *         canonical="https://example.com/page"
 *         siteName="My Site"
 *         titleTemplate="%s | My Site"
 *       />
 *       <main>Page content...</main>
 *     </>
 *   );
 * }
 * ```
 */
export function SEOHead({
  children,
  siteName,
  titleTemplate,
  twitterSite,
  locale,
  ...seoData
}: SEOHeadProps): React.ReactElement {
  const helmetProps = useSEO(seoData, {
    siteName,
    titleTemplate,
    twitterSite,
    locale,
  });

  return (
    <Helmet
      title={helmetProps.title}
      titleTemplate={helmetProps.titleTemplate}
    >
      {/* Meta tags */}
      {helmetProps.meta?.map((meta, index) => (
        <meta
          key={`${meta.name || meta.property}-${index}`}
          {...meta}
        />
      ))}

      {/* Link tags */}
      {helmetProps.link?.map((link, index) => (
        <link key={`${link.rel}-${index}`} {...link} />
      ))}

      {/* Script tags (JSON-LD) */}
      {helmetProps.script?.map((script, index) => (
        <script
          key={`script-${index}`}
          type={script.type}
          dangerouslySetInnerHTML={{ __html: script.innerHTML }}
        />
      ))}

      {/* Additional children */}
      {children}
    </Helmet>
  );
}

export default SEOHead;
