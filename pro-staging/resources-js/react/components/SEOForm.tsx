/**
 * SEO Form Component for React
 *
 * A comprehensive SEO editor with tabs, auto-save, and analysis integration.
 *
 * @package fibonoir/laravel-seo
 */

import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { useDebouncedCallback } from 'use-debounce';
import type { SEOFormProps, SEOData, Keyword, AnalysisReport, SEOFormState } from '../types/seo';
import { KeywordManager } from './KeywordManager';
import { SEOAnalyzer } from './SEOAnalyzer';
import { SEOPreview } from './SEOPreview';

type TabId = 'basic' | 'social' | 'advanced' | 'analysis';

interface Tab {
  id: TabId;
  label: string;
}

const tabs: Tab[] = [
  { id: 'basic', label: 'Basic SEO' },
  { id: 'social', label: 'Social Media' },
  { id: 'advanced', label: 'Advanced' },
  { id: 'analysis', label: 'Analysis' },
];

const TITLE_MAX_LENGTH = 60;
const DESCRIPTION_MAX_LENGTH = 160;

/**
 * Initialize form state from SEO data
 */
function initializeFormState(data?: Partial<SEOData>): SEOFormState {
  return {
    title: data?.title ?? '',
    description: data?.description ?? '',
    focusKeywords: (data?.focusKeywords ?? []) as Keyword[],
    canonical: data?.canonical ?? '',
    robots: typeof data?.robots === 'string' ? data.robots : 'index, follow',
    ogTitle: data?.ogTitle ?? data?.openGraph?.title ?? '',
    ogDescription: data?.ogDescription ?? data?.openGraph?.description ?? '',
    ogImage: data?.ogImage ?? data?.openGraph?.image ?? '',
    ogType: data?.ogType ?? data?.openGraph?.type ?? 'website',
    twitterCard: data?.twitterCardType ??
      (typeof data?.twitterCard === 'object' ? data.twitterCard.card : 'summary_large_image') ??
      'summary_large_image',
    twitterTitle: data?.twitterTitle ??
      (typeof data?.twitterCard === 'object' ? data.twitterCard.title : '') ?? '',
    twitterDescription: data?.twitterDescription ??
      (typeof data?.twitterCard === 'object' ? data.twitterCard.description : '') ?? '',
    twitterImage: data?.twitterImage ??
      (typeof data?.twitterCard === 'object' ? data.twitterCard.image : '') ?? '',
    schemaType: data?.schemaType ?? '',
    schemaJsonld: typeof data?.schemaJsonld === 'string'
      ? data.schemaJsonld
      : data?.schemaJsonld
        ? JSON.stringify(data.schemaJsonld, null, 2)
        : '',
  };
}

/**
 * Get CSRF token from meta tag
 */
function getCSRFToken(): string {
  if (typeof document === 'undefined') return '';
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

/**
 * Format date for display
 */
function formatDate(dateString: string): string {
  try {
    return new Intl.DateTimeFormat('en-US', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(dateString));
  } catch {
    return dateString;
  }
}

/**
 * SEO Form component
 */
export function SEOForm({
  modelType,
  modelId,
  initialData,
  saveEndpoint = '/api/seo',
  analyzeEndpoint = '/api/seo/analyze',
  autoSave = true,
  autoSaveDelay = 1500,
  onSaved,
  onError,
  onAnalyzed,
}: SEOFormProps): React.ReactElement {
  // State
  const [activeTab, setActiveTab] = useState<TabId>('basic');
  const [form, setForm] = useState<SEOFormState>(() => initializeFormState(initialData));
  const [saving, setSaving] = useState(false);
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [analyzing, setAnalyzing] = useState(false);
  const [analysisReport, setAnalysisReport] = useState<AnalysisReport | null>(
    initialData?.analysisReport ?? null
  );

  // Character count status
  const titleStatus = useMemo(() => {
    const length = form.title.length;
    if (length === 0) return 'empty';
    if (length > TITLE_MAX_LENGTH) return 'error';
    if (length > TITLE_MAX_LENGTH - 10) return 'warning';
    return 'good';
  }, [form.title]);

  const descriptionStatus = useMemo(() => {
    const length = form.description.length;
    if (length === 0) return 'empty';
    if (length > DESCRIPTION_MAX_LENGTH) return 'error';
    if (length > DESCRIPTION_MAX_LENGTH - 20) return 'warning';
    return 'good';
  }, [form.description]);

  // JSON-LD validation
  const jsonLdValid = useMemo(() => {
    if (!form.schemaJsonld) return true;
    try {
      JSON.parse(form.schemaJsonld);
      return true;
    } catch {
      return false;
    }
  }, [form.schemaJsonld]);

  // Preview URL
  const previewUrl = useMemo(() => {
    if (typeof window !== 'undefined') {
      return window.location.href;
    }
    return 'https://example.com/page';
  }, []);

  // Form to payload converter
  const formToPayload = useCallback((): Record<string, unknown> => {
    return {
      title: form.title || null,
      description: form.description || null,
      focus_keywords: form.focusKeywords.map((k) => ({
        keyword: k.keyword,
        is_primary: k.isPrimary,
        synonyms: k.synonyms,
      })),
      canonical: form.canonical || null,
      robots: form.robots,
      og_title: form.ogTitle || null,
      og_description: form.ogDescription || null,
      og_image: form.ogImage || null,
      og_type: form.ogType,
      twitter_card: form.twitterCard,
      twitter_title: form.twitterTitle || null,
      twitter_description: form.twitterDescription || null,
      twitter_image: form.twitterImage || null,
      schema_type: form.schemaType || null,
      schema_jsonld: form.schemaJsonld ? JSON.parse(form.schemaJsonld) : null,
    };
  }, [form]);

  // Save function
  const saveData = useCallback(async () => {
    if (saving) return;

    setSaving(true);

    try {
      const response = await fetch(saveEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCSRFToken(),
        },
        body: JSON.stringify({
          model_type: modelType,
          model_id: modelId,
          ...formToPayload(),
        }),
      });

      if (!response.ok) {
        throw new Error(`Failed to save SEO data: ${response.statusText}`);
      }

      const data: SEOData = await response.json();
      setLastSaved(new Date());
      onSaved?.(data);
    } catch (error) {
      onError?.(error as Error);
    } finally {
      setSaving(false);
    }
  }, [saving, saveEndpoint, modelType, modelId, formToPayload, onSaved, onError]);

  // Analyze function
  const runAnalysis = useCallback(async () => {
    if (analyzing) return;

    setAnalyzing(true);

    try {
      const response = await fetch(analyzeEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCSRFToken(),
        },
        body: JSON.stringify({
          model_type: modelType,
          model_id: modelId,
        }),
      });

      if (!response.ok) {
        throw new Error(`Failed to analyze: ${response.statusText}`);
      }

      const report: AnalysisReport = await response.json();
      setAnalysisReport(report);
      onAnalyzed?.(report);
    } catch (error) {
      onError?.(error as Error);
    } finally {
      setAnalyzing(false);
    }
  }, [analyzing, analyzeEndpoint, modelType, modelId, onAnalyzed, onError]);

  // Debounced auto-save
  const debouncedSave = useDebouncedCallback(() => {
    if (autoSave) {
      saveData();
    }
  }, autoSaveDelay);

  // Watch for form changes and auto-save
  useEffect(() => {
    debouncedSave();
  }, [form, debouncedSave]);

  // Update form field
  const updateField = useCallback(<K extends keyof SEOFormState>(
    field: K,
    value: SEOFormState[K]
  ) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  }, []);

  // Handle keywords change
  const handleKeywordsChange = useCallback((keywords: Keyword[]) => {
    updateField('focusKeywords', keywords);
  }, [updateField]);

  return (
    <div className="seo-form">
      {/* Tab Navigation */}
      <div className="seo-form__tabs">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            className={`seo-form__tab ${activeTab === tab.id ? 'seo-form__tab--active' : ''}`}
            onClick={() => setActiveTab(tab.id)}
          >
            {tab.label}
            {tab.id === 'analysis' && analysisReport && (
              <span className={`seo-form__score-badge seo-form__score-badge--${
                analysisReport.score >= 80 ? 'good' :
                analysisReport.score >= 50 ? 'warning' : 'bad'
              }`}>
                {analysisReport.score}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      <div className="seo-form__content">
        {/* Basic SEO Tab */}
        {activeTab === 'basic' && (
          <div className="seo-form__panel">
            {/* Meta Title */}
            <div className="seo-form__field">
              <label htmlFor="seo-title" className="seo-form__label">
                <span>Meta Title</span>
                <span className={`seo-form__char-count seo-form__char-count--${titleStatus}`}>
                  {form.title.length}/{TITLE_MAX_LENGTH}
                </span>
              </label>
              <input
                id="seo-title"
                type="text"
                value={form.title}
                onChange={(e) => updateField('title', e.target.value)}
                maxLength={70}
                placeholder="Enter page title..."
                className="seo-form__input"
              />
              <p className="seo-form__hint">
                Recommended: 50-60 characters for optimal display in search results.
              </p>
            </div>

            {/* Meta Description */}
            <div className="seo-form__field">
              <label htmlFor="seo-description" className="seo-form__label">
                <span>Meta Description</span>
                <span className={`seo-form__char-count seo-form__char-count--${descriptionStatus}`}>
                  {form.description.length}/{DESCRIPTION_MAX_LENGTH}
                </span>
              </label>
              <textarea
                id="seo-description"
                value={form.description}
                onChange={(e) => updateField('description', e.target.value)}
                rows={3}
                maxLength={200}
                placeholder="Enter meta description..."
                className="seo-form__textarea"
              />
              <p className="seo-form__hint">
                Recommended: 150-160 characters. Make it compelling to improve click-through rates.
              </p>
            </div>

            {/* Focus Keywords */}
            <div className="seo-form__field">
              <label className="seo-form__label">Focus Keywords</label>
              <KeywordManager
                value={form.focusKeywords}
                onChange={handleKeywordsChange}
                maxKeywords={5}
                maxSynonyms={5}
              />
            </div>
          </div>
        )}

        {/* Social Media Tab */}
        {activeTab === 'social' && (
          <div className="seo-form__panel">
            {/* Open Graph Section */}
            <fieldset className="seo-form__fieldset">
              <legend className="seo-form__legend">
                <svg className="seo-form__legend-icon" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
                </svg>
                Open Graph (Facebook, LinkedIn)
              </legend>

              <div className="seo-form__field">
                <label htmlFor="og-title" className="seo-form__label">OG Title</label>
                <input
                  id="og-title"
                  type="text"
                  value={form.ogTitle}
                  onChange={(e) => updateField('ogTitle', e.target.value)}
                  maxLength={70}
                  placeholder="Defaults to meta title"
                  className="seo-form__input"
                />
              </div>

              <div className="seo-form__field">
                <label htmlFor="og-description" className="seo-form__label">OG Description</label>
                <textarea
                  id="og-description"
                  value={form.ogDescription}
                  onChange={(e) => updateField('ogDescription', e.target.value)}
                  rows={2}
                  maxLength={200}
                  placeholder="Defaults to meta description"
                  className="seo-form__textarea"
                />
              </div>

              <div className="seo-form__field">
                <label htmlFor="og-image" className="seo-form__label">OG Image URL</label>
                <input
                  id="og-image"
                  type="url"
                  value={form.ogImage}
                  onChange={(e) => updateField('ogImage', e.target.value)}
                  placeholder="https://example.com/image.jpg"
                  className="seo-form__input"
                />
                <p className="seo-form__hint">Recommended size: 1200x630 pixels.</p>
              </div>

              <div className="seo-form__field">
                <label htmlFor="og-type" className="seo-form__label">OG Type</label>
                <select
                  id="og-type"
                  value={form.ogType}
                  onChange={(e) => updateField('ogType', e.target.value)}
                  className="seo-form__select"
                >
                  <option value="website">Website</option>
                  <option value="article">Article</option>
                  <option value="product">Product</option>
                  <option value="profile">Profile</option>
                  <option value="book">Book</option>
                </select>
              </div>
            </fieldset>

            {/* Twitter Card Section */}
            <fieldset className="seo-form__fieldset">
              <legend className="seo-form__legend">
                <svg className="seo-form__legend-icon" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                </svg>
                Twitter Card
              </legend>

              <div className="seo-form__field">
                <label htmlFor="twitter-card" className="seo-form__label">Card Type</label>
                <select
                  id="twitter-card"
                  value={form.twitterCard}
                  onChange={(e) => updateField('twitterCard', e.target.value)}
                  className="seo-form__select"
                >
                  <option value="summary">Summary</option>
                  <option value="summary_large_image">Summary with Large Image</option>
                  <option value="app">App</option>
                  <option value="player">Player</option>
                </select>
              </div>

              <div className="seo-form__field">
                <label htmlFor="twitter-title" className="seo-form__label">Twitter Title</label>
                <input
                  id="twitter-title"
                  type="text"
                  value={form.twitterTitle}
                  onChange={(e) => updateField('twitterTitle', e.target.value)}
                  maxLength={70}
                  placeholder="Defaults to OG title"
                  className="seo-form__input"
                />
              </div>

              <div className="seo-form__field">
                <label htmlFor="twitter-description" className="seo-form__label">Twitter Description</label>
                <textarea
                  id="twitter-description"
                  value={form.twitterDescription}
                  onChange={(e) => updateField('twitterDescription', e.target.value)}
                  rows={2}
                  maxLength={200}
                  placeholder="Defaults to OG description"
                  className="seo-form__textarea"
                />
              </div>

              <div className="seo-form__field">
                <label htmlFor="twitter-image" className="seo-form__label">Twitter Image URL</label>
                <input
                  id="twitter-image"
                  type="url"
                  value={form.twitterImage}
                  onChange={(e) => updateField('twitterImage', e.target.value)}
                  placeholder="Defaults to OG image"
                  className="seo-form__input"
                />
                <p className="seo-form__hint">Recommended size: 1200x600 pixels (2:1 ratio).</p>
              </div>
            </fieldset>
          </div>
        )}

        {/* Advanced Tab */}
        {activeTab === 'advanced' && (
          <div className="seo-form__panel">
            <div className="seo-form__field">
              <label htmlFor="canonical" className="seo-form__label">Canonical URL</label>
              <input
                id="canonical"
                type="url"
                value={form.canonical}
                onChange={(e) => updateField('canonical', e.target.value)}
                placeholder="https://example.com/page"
                className="seo-form__input"
              />
              <p className="seo-form__hint">Use this to prevent duplicate content issues.</p>
            </div>

            <div className="seo-form__field">
              <label htmlFor="robots" className="seo-form__label">Robots Directive</label>
              <select
                id="robots"
                value={form.robots}
                onChange={(e) => updateField('robots', e.target.value)}
                className="seo-form__select"
              >
                <option value="index, follow">Index, Follow (Default)</option>
                <option value="index, nofollow">Index, No Follow</option>
                <option value="noindex, follow">No Index, Follow</option>
                <option value="noindex, nofollow">No Index, No Follow</option>
              </select>
              <p className="seo-form__hint">Control how search engines index this page.</p>
            </div>

            <div className="seo-form__field">
              <label htmlFor="schema-type" className="seo-form__label">Schema Type</label>
              <select
                id="schema-type"
                value={form.schemaType}
                onChange={(e) => updateField('schemaType', e.target.value)}
                className="seo-form__select"
              >
                <option value="">None</option>
                <option value="Article">Article</option>
                <option value="BlogPosting">Blog Post</option>
                <option value="NewsArticle">News Article</option>
                <option value="Product">Product</option>
                <option value="LocalBusiness">Local Business</option>
                <option value="Organization">Organization</option>
                <option value="Person">Person</option>
                <option value="Event">Event</option>
                <option value="Recipe">Recipe</option>
                <option value="FAQPage">FAQ Page</option>
                <option value="HowTo">How-To</option>
                <option value="Review">Review</option>
                <option value="Course">Course</option>
                <option value="SoftwareApplication">Software Application</option>
                <option value="WebPage">Web Page</option>
              </select>
            </div>

            <div className="seo-form__field">
              <label htmlFor="schema-jsonld" className="seo-form__label">
                <span>Custom JSON-LD Schema</span>
                {!jsonLdValid && (
                  <span className="seo-form__error-badge">Invalid JSON</span>
                )}
              </label>
              <textarea
                id="schema-jsonld"
                value={form.schemaJsonld}
                onChange={(e) => updateField('schemaJsonld', e.target.value)}
                rows={8}
                placeholder='{"@context": "https://schema.org", "@type": "Article", ...}'
                className={`seo-form__textarea seo-form__textarea--code ${!jsonLdValid ? 'seo-form__textarea--error' : ''}`}
              />
              <p className="seo-form__hint">
                Override the auto-generated schema with custom JSON-LD.{' '}
                <a href="https://schema.org/" target="_blank" rel="noopener noreferrer" className="seo-form__link">
                  Learn more about Schema.org
                </a>
              </p>
            </div>
          </div>
        )}

        {/* Analysis Tab */}
        {activeTab === 'analysis' && (
          <div className="seo-form__panel">
            <SEOAnalyzer
              score={analysisReport?.score ?? 0}
              results={analysisReport?.results ?? []}
              loading={analyzing}
            />
            {analysisReport?.analyzedAt && (
              <p className="seo-form__timestamp">
                Last analyzed: {formatDate(analysisReport.analyzedAt)}
              </p>
            )}
            <button
              type="button"
              className="seo-form__button seo-form__button--secondary"
              onClick={runAnalysis}
              disabled={analyzing}
            >
              {analyzing ? (
                <>
                  <svg className="seo-form__spinner" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" fill="none" opacity="0.25" />
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  Analyzing...
                </>
              ) : (
                'Run Analysis'
              )}
            </button>
          </div>
        )}
      </div>

      {/* Preview Section */}
      <div className="seo-form__preview">
        <h3 className="seo-form__preview-title">Search Preview</h3>
        <SEOPreview
          title={form.title || 'Page Title'}
          description={form.description || 'Page description will appear here...'}
          url={form.canonical || previewUrl}
          ogImage={form.ogImage}
        />
      </div>

      {/* Auto-save indicator */}
      {autoSave && (
        <div className="seo-form__autosave">
          {saving && (
            <span className="seo-form__autosave-status seo-form__autosave-status--saving">
              Saving...
            </span>
          )}
          {!saving && lastSaved && (
            <span className="seo-form__autosave-status seo-form__autosave-status--saved">
              ✓ Saved
            </span>
          )}
        </div>
      )}

      <style>{`
        .seo-form {
          --seo-primary: #4f46e5;
          --seo-primary-hover: #4338ca;
          --seo-success: #10b981;
          --seo-warning: #f59e0b;
          --seo-error: #ef4444;
          --seo-gray-50: #f9fafb;
          --seo-gray-100: #f3f4f6;
          --seo-gray-200: #e5e7eb;
          --seo-gray-300: #d1d5db;
          --seo-gray-400: #9ca3af;
          --seo-gray-500: #6b7280;
          --seo-gray-600: #4b5563;
          --seo-gray-700: #374151;
          --seo-gray-800: #1f2937;
          --seo-radius: 0.5rem;
          --seo-transition: 150ms ease;
          font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          color: var(--seo-gray-800);
        }

        .seo-form__tabs {
          display: flex;
          gap: 0.25rem;
          border-bottom: 1px solid var(--seo-gray-200);
          margin-bottom: 1.5rem;
        }

        .seo-form__tab {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          padding: 0.75rem 1rem;
          font-size: 0.875rem;
          font-weight: 500;
          color: var(--seo-gray-500);
          background: transparent;
          border: none;
          border-bottom: 2px solid transparent;
          cursor: pointer;
          transition: all var(--seo-transition);
          margin-bottom: -1px;
        }

        .seo-form__tab:hover { color: var(--seo-gray-700); }

        .seo-form__tab--active {
          color: var(--seo-primary);
          border-bottom-color: var(--seo-primary);
        }

        .seo-form__score-badge {
          padding: 0.125rem 0.5rem;
          font-size: 0.75rem;
          font-weight: 600;
          border-radius: 9999px;
        }

        .seo-form__score-badge--good { background: #d1fae5; color: #065f46; }
        .seo-form__score-badge--warning { background: #fef3c7; color: #92400e; }
        .seo-form__score-badge--bad { background: #fee2e2; color: #991b1b; }

        .seo-form__content { min-height: 400px; }

        .seo-form__panel { animation: fadeIn 200ms ease; }

        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(4px); }
          to { opacity: 1; transform: translateY(0); }
        }

        .seo-form__field { margin-bottom: 1.25rem; }

        .seo-form__label {
          display: flex;
          justify-content: space-between;
          align-items: center;
          font-size: 0.875rem;
          font-weight: 500;
          color: var(--seo-gray-700);
          margin-bottom: 0.5rem;
        }

        .seo-form__input,
        .seo-form__textarea,
        .seo-form__select {
          width: 100%;
          padding: 0.625rem 0.875rem;
          font-size: 0.9375rem;
          color: var(--seo-gray-800);
          background: white;
          border: 1px solid var(--seo-gray-300);
          border-radius: var(--seo-radius);
          transition: border-color var(--seo-transition), box-shadow var(--seo-transition);
        }

        .seo-form__input:focus,
        .seo-form__textarea:focus,
        .seo-form__select:focus {
          outline: none;
          border-color: var(--seo-primary);
          box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .seo-form__textarea { resize: vertical; min-height: 80px; }
        .seo-form__textarea--code { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.8125rem; }
        .seo-form__textarea--error { border-color: var(--seo-error); }

        .seo-form__hint {
          margin-top: 0.375rem;
          font-size: 0.8125rem;
          color: var(--seo-gray-500);
        }

        .seo-form__link {
          color: var(--seo-primary);
          text-decoration: none;
        }

        .seo-form__link:hover { text-decoration: underline; }

        .seo-form__char-count {
          font-size: 0.75rem;
          font-weight: 400;
        }

        .seo-form__char-count--empty,
        .seo-form__char-count--good { color: var(--seo-gray-400); }
        .seo-form__char-count--warning { color: var(--seo-warning); }
        .seo-form__char-count--error { color: var(--seo-error); }

        .seo-form__error-badge {
          padding: 0.125rem 0.5rem;
          font-size: 0.6875rem;
          font-weight: 600;
          text-transform: uppercase;
          letter-spacing: 0.025em;
          color: white;
          background: var(--seo-error);
          border-radius: 9999px;
        }

        .seo-form__fieldset {
          margin: 0 0 1.5rem;
          padding: 1.25rem;
          border: 1px solid var(--seo-gray-200);
          border-radius: var(--seo-radius);
          background: var(--seo-gray-50);
        }

        .seo-form__legend {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          padding: 0 0.5rem;
          font-size: 0.9375rem;
          font-weight: 600;
          color: var(--seo-gray-700);
        }

        .seo-form__legend-icon { width: 1.125rem; height: 1.125rem; }

        .seo-form__button {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 0.5rem;
          padding: 0.625rem 1.25rem;
          font-size: 0.875rem;
          font-weight: 500;
          border-radius: var(--seo-radius);
          cursor: pointer;
          transition: all var(--seo-transition);
        }

        .seo-form__button:disabled { opacity: 0.6; cursor: not-allowed; }

        .seo-form__button--secondary {
          color: var(--seo-gray-700);
          background: white;
          border: 1px solid var(--seo-gray-300);
        }

        .seo-form__button--secondary:hover:not(:disabled) {
          background: var(--seo-gray-50);
          border-color: var(--seo-gray-400);
        }

        .seo-form__spinner {
          width: 1rem;
          height: 1rem;
          animation: spin 1s linear infinite;
        }

        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }

        .seo-form__preview {
          margin-top: 2rem;
          padding-top: 2rem;
          border-top: 1px solid var(--seo-gray-200);
        }

        .seo-form__preview-title {
          font-size: 1rem;
          font-weight: 600;
          color: var(--seo-gray-700);
          margin: 0 0 1rem;
        }

        .seo-form__timestamp {
          margin: 1rem 0;
          font-size: 0.8125rem;
          color: var(--seo-gray-500);
        }

        .seo-form__autosave {
          position: fixed;
          bottom: 1.5rem;
          right: 1.5rem;
          z-index: 50;
        }

        .seo-form__autosave-status {
          display: inline-flex;
          align-items: center;
          gap: 0.375rem;
          padding: 0.5rem 1rem;
          font-size: 0.8125rem;
          font-weight: 500;
          border-radius: 9999px;
          box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
          background: white;
        }

        .seo-form__autosave-status--saving { color: var(--seo-gray-600); }
        .seo-form__autosave-status--saved { color: var(--seo-success); }
      `}</style>
    </div>
  );
}

export default SEOForm;
