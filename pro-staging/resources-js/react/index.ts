/**
 * Laravel SEO - React + Inertia Components
 *
 * @package fibonoir/laravel-seo
 *
 * @example
 * ```tsx
 * // Import components
 * import { SEOForm, SEOAnalyzer, SEOPreview, KeywordManager, SEOHead } from '@fibonoir/laravel-seo/react';
 *
 * // Import hook
 * import { useSEO } from '@fibonoir/laravel-seo/react';
 *
 * // Import types
 * import type { SEOData, RuleResult, Keyword } from '@fibonoir/laravel-seo/react';
 * ```
 */

// Components
export { SEOForm } from './components/SEOForm';
export { SEOAnalyzer } from './components/SEOAnalyzer';
export { SEOPreview } from './components/SEOPreview';
export { KeywordManager } from './components/KeywordManager';
export { SEOHead } from './components/SEOHead';

// Hooks
export { useSEO } from './hooks/useSEO';
export type { UseSEOOptions } from './hooks/useSEO';

// Types
export type {
  // Core types
  SEOData,
  SEOFormState,
  RuleResult,
  AnalysisReport,
  Keyword,
  RuleStatus,
  Priority,

  // Metadata types
  OpenGraphData,
  TwitterCardData,
  RobotsDirective,

  // Component props
  SEOFormProps,
  SEOAnalyzerProps,
  SEOPreviewProps,
  KeywordManagerProps,
  SEOHeadProps,
  HelmetProps,
} from './types/seo';
