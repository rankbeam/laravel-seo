/**
 * Laravel SEO - Vue + Inertia Components
 *
 * @package fibonoir/laravel-seo
 *
 * @example
 * ```ts
 * // Import components
 * import { SEOForm, SEOAnalyzer, SEOPreview, KeywordManager } from '@fibonoir/laravel-seo/vue';
 *
 * // Import composable
 * import { useSEO } from '@fibonoir/laravel-seo/vue';
 *
 * // Import types
 * import type { SEOData, RuleResult, Keyword } from '@fibonoir/laravel-seo/vue';
 * ```
 */

// Components
export { default as SEOForm } from './components/SEOForm.vue';
export { default as SEOAnalyzer } from './components/SEOAnalyzer.vue';
export { default as SEOPreview } from './components/SEOPreview.vue';
export { default as KeywordManager } from './components/KeywordManager.vue';

// Composables
export { useSEO } from './composables/useSEO';
export type { UseSEOOptions } from './composables/useSEO';

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

  // Events
  SEOFormEmits,
  KeywordManagerEmits,
} from './types/seo';
