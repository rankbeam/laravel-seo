/**
 * SEO Types for React + Inertia
 *
 * @package fibonoir/laravel-seo
 */

/**
 * Status of an SEO analysis rule result
 */
export type RuleStatus = 'pass' | 'warning' | 'fail' | 'skip';

/**
 * Priority level for SEO issues
 */
export type Priority = 'critical' | 'high' | 'medium' | 'low';

/**
 * Result of a single SEO analysis rule
 */
export interface RuleResult {
  /** Unique rule identifier */
  rule: string;
  /** Human-readable rule name */
  name: string;
  /** Result status */
  status: RuleStatus;
  /** Detailed message explaining the result */
  message: string;
  /** Priority level of this rule */
  priority: Priority;
  /** Category grouping (e.g., 'meta', 'content', 'technical') */
  category: string;
  /** Score impact (0-100) */
  score: number;
  /** Maximum possible score for this rule */
  maxScore: number;
  /** Additional metadata */
  data?: Record<string, unknown>;
  /** Suggestions for improvement */
  suggestions?: string[];
}

/**
 * Complete SEO analysis report
 */
export interface AnalysisReport {
  /** Overall SEO score (0-100) */
  score: number;
  /** Individual rule results */
  results: RuleResult[];
  /** Timestamp when analysis was performed */
  analyzedAt: string | null;
  /** URL that was analyzed */
  url?: string;
  /** Analysis version */
  version?: string;
}

/**
 * Keyword with synonyms
 */
export interface Keyword {
  /** Unique identifier */
  id: string;
  /** The keyword text */
  keyword: string;
  /** Whether this is the primary focus keyword */
  isPrimary: boolean;
  /** Related synonyms */
  synonyms: string[];
}

/**
 * Open Graph metadata
 */
export interface OpenGraphData {
  /** OG title */
  title?: string;
  /** OG description */
  description?: string;
  /** OG image URL */
  image?: string;
  /** OG type (website, article, etc.) */
  type?: 'website' | 'article' | 'product' | 'profile' | 'book' | string;
  /** Site name */
  siteName?: string;
  /** Locale */
  locale?: string;
}

/**
 * Twitter Card metadata
 */
export interface TwitterCardData {
  /** Card type */
  card?: 'summary' | 'summary_large_image' | 'app' | 'player';
  /** Twitter title */
  title?: string;
  /** Twitter description */
  description?: string;
  /** Twitter image URL */
  image?: string;
  /** Twitter site handle (e.g., @example) */
  site?: string;
  /** Twitter creator handle */
  creator?: string;
}

/**
 * Robots directive
 */
export interface RobotsDirective {
  /** Index status */
  index: boolean;
  /** Follow status */
  follow: boolean;
  /** Additional directives */
  additional?: string[];
}

/**
 * Complete SEO data for a model/page
 */
export interface SEOData {
  /** SEO record ID */
  id?: number | string;
  /** Model type (e.g., 'App\\Models\\Post') */
  modelType?: string;
  /** Model ID */
  modelId?: number | string;

  // Basic SEO
  /** Meta title */
  title?: string;
  /** Meta description */
  description?: string;
  /** Focus keywords */
  focusKeywords?: Keyword[];
  /** Canonical URL */
  canonical?: string;
  /** Robots directive string or object */
  robots?: string | RobotsDirective;

  // Open Graph
  /** Open Graph data */
  openGraph?: OpenGraphData;
  /** Legacy: OG title */
  ogTitle?: string;
  /** Legacy: OG description */
  ogDescription?: string;
  /** Legacy: OG image */
  ogImage?: string;
  /** Legacy: OG type */
  ogType?: string;

  // Twitter Card
  /** Twitter Card data */
  twitterCard?: TwitterCardData;
  /** Legacy: Twitter card type */
  twitterCardType?: string;
  /** Legacy: Twitter title */
  twitterTitle?: string;
  /** Legacy: Twitter description */
  twitterDescription?: string;
  /** Legacy: Twitter image */
  twitterImage?: string;

  // Schema / Structured Data
  /** Schema.org type */
  schemaType?: string;
  /** Custom JSON-LD schema */
  schemaJsonld?: string | Record<string, unknown>;

  // Analysis
  /** Current SEO score */
  seoScore?: number;
  /** Analysis report */
  analysisReport?: AnalysisReport;
  /** Last analysis timestamp */
  analyzedAt?: string;

  // Timestamps
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Form state for SEO editing
 */
export interface SEOFormState {
  title: string;
  description: string;
  focusKeywords: Keyword[];
  canonical: string;
  robots: string;
  ogTitle: string;
  ogDescription: string;
  ogImage: string;
  ogType: string;
  twitterCard: string;
  twitterTitle: string;
  twitterDescription: string;
  twitterImage: string;
  schemaType: string;
  schemaJsonld: string;
}

/**
 * Props for SEO components
 */
export interface SEOFormProps {
  modelType: string;
  modelId: number | string;
  initialData?: Partial<SEOData>;
  saveEndpoint?: string;
  analyzeEndpoint?: string;
  autoSave?: boolean;
  autoSaveDelay?: number;
  onSaved?: (data: SEOData) => void;
  onError?: (error: Error) => void;
  onAnalyzed?: (report: AnalysisReport) => void;
}

export interface SEOAnalyzerProps {
  score: number;
  results: RuleResult[];
  loading?: boolean;
}

export interface KeywordManagerProps {
  value: Keyword[];
  onChange: (keywords: Keyword[]) => void;
  maxKeywords?: number;
  maxSynonyms?: number;
}

export interface SEOPreviewProps {
  title: string;
  description: string;
  url: string;
  siteName?: string;
  ogImage?: string;
}

export interface SEOHeadProps extends SEOData {
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
 * Helmet-compatible meta tag props
 */
export interface HelmetProps {
  title?: string;
  titleTemplate?: string;
  meta?: Array<{
    name?: string;
    property?: string;
    content: string;
  }>;
  link?: Array<{
    rel: string;
    href: string;
  }>;
  script?: Array<{
    type: string;
    innerHTML: string;
  }>;
}
