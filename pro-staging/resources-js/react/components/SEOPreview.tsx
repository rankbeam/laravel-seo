/**
 * SEO Preview Component for React
 *
 * Shows Google SERP and social card previews.
 *
 * @package fibonoir/laravel-seo
 */

import React, { useState, useMemo } from 'react';
import type { SEOPreviewProps } from '../types/seo';

type PreviewType = 'google' | 'og' | 'twitter';

interface PreviewTab {
  id: PreviewType;
  label: string;
}

const previewTabs: PreviewTab[] = [
  { id: 'google', label: 'Google' },
  { id: 'og', label: 'Facebook/LinkedIn' },
  { id: 'twitter', label: 'X (Twitter)' },
];

/**
 * SEO Preview component
 */
export function SEOPreview({
  title,
  description,
  url,
  siteName = '',
  ogImage = '',
}: SEOPreviewProps): React.ReactElement {
  const [activePreview, setActivePreview] = useState<PreviewType>('google');
  const [imageError, setImageError] = useState(false);

  // URL parsing
  const displayUrl = useMemo(() => {
    try {
      const urlObj = new URL(url);
      return urlObj.origin;
    } catch {
      return url;
    }
  }, [url]);

  const displayDomain = useMemo(() => {
    try {
      const urlObj = new URL(url);
      return urlObj.hostname;
    } catch {
      return url;
    }
  }, [url]);

  const urlPath = useMemo(() => {
    try {
      const urlObj = new URL(url);
      const path = urlObj.pathname;
      return path === '/' ? '' : path.substring(1);
    } catch {
      return '';
    }
  }, [url]);

  // Truncated values for different platforms
  const truncatedTitle = useMemo(() => {
    const maxLength = 60;
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength - 3) + '...';
  }, [title]);

  const truncatedDescription = useMemo(() => {
    const maxLength = 160;
    if (description.length <= maxLength) return description;
    return description.substring(0, maxLength - 3) + '...';
  }, [description]);

  const truncatedOgTitle = useMemo(() => {
    const maxLength = 65;
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength - 3) + '...';
  }, [title]);

  const truncatedOgDescription = useMemo(() => {
    const maxLength = 155;
    if (description.length <= maxLength) return description;
    return description.substring(0, maxLength - 3) + '...';
  }, [description]);

  const truncatedTwitterTitle = useMemo(() => {
    const maxLength = 70;
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength - 3) + '...';
  }, [title]);

  const truncatedTwitterDescription = useMemo(() => {
    const maxLength = 200;
    if (description.length <= maxLength) return description;
    return description.substring(0, maxLength - 3) + '...';
  }, [description]);

  // Handle image error
  const handleImageError = () => {
    setImageError(true);
  };

  return (
    <div className="seo-preview">
      {/* Preview Type Tabs */}
      <div className="seo-preview__tabs">
        {previewTabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            className={`seo-preview__tab ${activePreview === tab.id ? 'seo-preview__tab--active' : ''}`}
            onClick={() => setActivePreview(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Google SERP Preview */}
      {activePreview === 'google' && (
        <div className="seo-preview__panel">
          <div className="seo-preview__google">
            <div className="seo-preview__google-favicon">
              <svg viewBox="0 0 16 16" fill="currentColor">
                <circle cx="8" cy="8" r="7" fill="#e5e7eb" />
                <path d="M8 4a4 4 0 100 8 4 4 0 000-8z" fill="#9ca3af" />
              </svg>
            </div>
            <div className="seo-preview__google-content">
              <div className="seo-preview__google-breadcrumb">
                <span className="seo-preview__google-url">{displayUrl}</span>
                {urlPath && (
                  <>
                    <span className="seo-preview__google-chevron">›</span>
                    <span className="seo-preview__google-path">{urlPath}</span>
                  </>
                )}
              </div>
              <h3 className="seo-preview__google-title">{truncatedTitle}</h3>
              <p className="seo-preview__google-description">{truncatedDescription}</p>
            </div>
          </div>
          <p className="seo-preview__note">
            This is an approximation of how your page may appear in Google search results.
          </p>
        </div>
      )}

      {/* Open Graph Preview */}
      {activePreview === 'og' && (
        <div className="seo-preview__panel">
          <div className="seo-preview__og">
            {ogImage && !imageError ? (
              <div className="seo-preview__og-image">
                <img src={ogImage} alt={title} onError={handleImageError} />
              </div>
            ) : (
              <div className="seo-preview__og-image seo-preview__og-image--placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
                <span>No image set</span>
              </div>
            )}
            <div className="seo-preview__og-content">
              <p className="seo-preview__og-site">{siteName || displayDomain}</p>
              <h3 className="seo-preview__og-title">{truncatedOgTitle}</h3>
              <p className="seo-preview__og-description">{truncatedOgDescription}</p>
            </div>
          </div>
          <p className="seo-preview__note">
            Preview of how your page may appear when shared on Facebook and LinkedIn.
          </p>
        </div>
      )}

      {/* Twitter Card Preview */}
      {activePreview === 'twitter' && (
        <div className="seo-preview__panel">
          <div className="seo-preview__twitter">
            {ogImage && !imageError ? (
              <div className="seo-preview__twitter-image">
                <img src={ogImage} alt={title} onError={handleImageError} />
              </div>
            ) : (
              <div className="seo-preview__twitter-image seo-preview__twitter-image--placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
              </div>
            )}
            <div className="seo-preview__twitter-content">
              <h3 className="seo-preview__twitter-title">{truncatedTwitterTitle}</h3>
              <p className="seo-preview__twitter-description">{truncatedTwitterDescription}</p>
              <p className="seo-preview__twitter-domain">
                <svg viewBox="0 0 16 16" fill="currentColor">
                  <path d="M8 0a8 8 0 100 16A8 8 0 008 0zm0 14.5a6.5 6.5 0 110-13 6.5 6.5 0 010 13z" opacity="0.5" />
                </svg>
                {displayDomain}
              </p>
            </div>
          </div>
          <p className="seo-preview__note">
            Preview of how your page may appear when shared on X (Twitter).
          </p>
        </div>
      )}

      <style>{`
        .seo-preview {
          --preview-primary: #4f46e5;
          --preview-google-blue: #1a0dab;
          --preview-google-green: #006621;
          --preview-gray-50: #f9fafb;
          --preview-gray-100: #f3f4f6;
          --preview-gray-200: #e5e7eb;
          --preview-gray-300: #d1d5db;
          --preview-gray-400: #9ca3af;
          --preview-gray-500: #6b7280;
          --preview-gray-600: #4b5563;
          --preview-gray-700: #374151;
          --preview-gray-800: #1f2937;
          --preview-radius: 0.5rem;
          --preview-transition: 150ms ease;
          font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .seo-preview__tabs {
          display: flex;
          gap: 0.25rem;
          margin-bottom: 1rem;
          padding: 0.25rem;
          background: var(--preview-gray-100);
          border-radius: var(--preview-radius);
        }

        .seo-preview__tab {
          flex: 1;
          padding: 0.5rem 1rem;
          font-size: 0.8125rem;
          font-weight: 500;
          color: var(--preview-gray-600);
          background: transparent;
          border: none;
          border-radius: calc(var(--preview-radius) - 2px);
          cursor: pointer;
          transition: all var(--preview-transition);
        }

        .seo-preview__tab:hover {
          color: var(--preview-gray-800);
        }

        .seo-preview__tab--active {
          color: var(--preview-gray-800);
          background: white;
          box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .seo-preview__panel {
          animation: fadeIn 200ms ease;
        }

        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }

        .seo-preview__google {
          display: flex;
          gap: 0.875rem;
          padding: 1rem;
          background: white;
          border: 1px solid var(--preview-gray-200);
          border-radius: var(--preview-radius);
        }

        .seo-preview__google-favicon {
          flex-shrink: 0;
          width: 1.75rem;
          height: 1.75rem;
        }

        .seo-preview__google-favicon svg {
          width: 100%;
          height: 100%;
        }

        .seo-preview__google-content {
          min-width: 0;
        }

        .seo-preview__google-breadcrumb {
          display: flex;
          align-items: center;
          gap: 0.25rem;
          font-size: 0.75rem;
          color: var(--preview-gray-500);
          margin-bottom: 0.25rem;
        }

        .seo-preview__google-url {
          color: var(--preview-gray-700);
        }

        .seo-preview__google-chevron {
          color: var(--preview-gray-400);
        }

        .seo-preview__google-path {
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }

        .seo-preview__google-title {
          font-family: Arial, sans-serif;
          font-size: 1.125rem;
          font-weight: 400;
          line-height: 1.3;
          color: var(--preview-google-blue);
          margin: 0 0 0.375rem;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          cursor: pointer;
        }

        .seo-preview__google-title:hover {
          text-decoration: underline;
        }

        .seo-preview__google-description {
          font-family: Arial, sans-serif;
          font-size: 0.875rem;
          line-height: 1.5;
          color: var(--preview-gray-600);
          margin: 0;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .seo-preview__og {
          overflow: hidden;
          background: var(--preview-gray-50);
          border: 1px solid var(--preview-gray-200);
          border-radius: var(--preview-radius);
        }

        .seo-preview__og-image {
          aspect-ratio: 1.91 / 1;
          background: var(--preview-gray-200);
          overflow: hidden;
        }

        .seo-preview__og-image img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .seo-preview__og-image--placeholder {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          gap: 0.5rem;
          color: var(--preview-gray-400);
          font-size: 0.8125rem;
        }

        .seo-preview__og-image--placeholder svg {
          width: 2.5rem;
          height: 2.5rem;
        }

        .seo-preview__og-content {
          padding: 0.875rem 1rem;
          border-top: 1px solid var(--preview-gray-200);
        }

        .seo-preview__og-site {
          font-size: 0.6875rem;
          font-weight: 500;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          color: var(--preview-gray-500);
          margin: 0 0 0.375rem;
        }

        .seo-preview__og-title {
          font-size: 1rem;
          font-weight: 600;
          line-height: 1.3;
          color: var(--preview-gray-800);
          margin: 0 0 0.25rem;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .seo-preview__og-description {
          font-size: 0.8125rem;
          line-height: 1.4;
          color: var(--preview-gray-500);
          margin: 0;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .seo-preview__twitter {
          overflow: hidden;
          background: white;
          border: 1px solid var(--preview-gray-200);
          border-radius: var(--preview-radius);
        }

        .seo-preview__twitter-image {
          aspect-ratio: 2 / 1;
          background: var(--preview-gray-200);
          overflow: hidden;
        }

        .seo-preview__twitter-image img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .seo-preview__twitter-image--placeholder {
          display: flex;
          align-items: center;
          justify-content: center;
          color: var(--preview-gray-400);
        }

        .seo-preview__twitter-image--placeholder svg {
          width: 3rem;
          height: 3rem;
        }

        .seo-preview__twitter-content {
          padding: 0.875rem 1rem;
        }

        .seo-preview__twitter-title {
          font-size: 0.9375rem;
          font-weight: 600;
          line-height: 1.3;
          color: var(--preview-gray-800);
          margin: 0 0 0.25rem;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .seo-preview__twitter-description {
          font-size: 0.8125rem;
          line-height: 1.4;
          color: var(--preview-gray-500);
          margin: 0 0 0.5rem;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .seo-preview__twitter-domain {
          display: flex;
          align-items: center;
          gap: 0.375rem;
          font-size: 0.8125rem;
          color: var(--preview-gray-400);
          margin: 0;
        }

        .seo-preview__twitter-domain svg {
          width: 0.875rem;
          height: 0.875rem;
        }

        .seo-preview__note {
          margin: 0.75rem 0 0;
          padding: 0.75rem;
          font-size: 0.75rem;
          color: var(--preview-gray-500);
          background: var(--preview-gray-50);
          border-radius: calc(var(--preview-radius) - 2px);
          text-align: center;
        }
      `}</style>
    </div>
  );
}

export default SEOPreview;
