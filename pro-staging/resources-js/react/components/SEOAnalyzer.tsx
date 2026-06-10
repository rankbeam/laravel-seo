/**
 * SEO Analyzer Component for React
 *
 * Displays SEO analysis results with score gauge and categorized rule results.
 *
 * @package fibonoir/laravel-seo
 */

import React, { useState, useMemo } from 'react';
import type { RuleResult, SEOAnalyzerProps } from '../types/seo';

// Icons as inline SVGs for zero dependencies
const CheckCircleIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg className={className} viewBox="0 0 20 20" fill="currentColor">
    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clipRule="evenodd" />
  </svg>
);

const XCircleIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg className={className} viewBox="0 0 20 20" fill="currentColor">
    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" />
  </svg>
);

const ExclamationTriangleIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg className={className} viewBox="0 0 20 20" fill="currentColor">
    <path fillRule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
  </svg>
);

const ChevronDownIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg className={className} viewBox="0 0 20 20" fill="currentColor">
    <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clipRule="evenodd" />
  </svg>
);

/**
 * Accordion section component for result groups
 */
interface AccordionProps {
  title: string;
  count: number;
  icon: React.ReactNode;
  colorClass: string;
  defaultOpen?: boolean;
  children: React.ReactNode;
}

const Accordion: React.FC<AccordionProps> = ({
  title,
  count,
  icon,
  colorClass,
  defaultOpen = false,
  children,
}) => {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <div className="seo-analyzer-accordion">
      <button
        type="button"
        className={`seo-analyzer-accordion__header ${colorClass}`}
        onClick={() => setIsOpen(!isOpen)}
        aria-expanded={isOpen}
      >
        <div className="seo-analyzer-accordion__title">
          {icon}
          <span>{title}</span>
        </div>
        <div className="seo-analyzer-accordion__meta">
          <span className={`seo-analyzer-accordion__count ${colorClass}`}>
            {count}
          </span>
          <ChevronDownIcon
            className={`seo-analyzer-accordion__chevron ${isOpen ? 'seo-analyzer-accordion__chevron--open' : ''}`}
          />
        </div>
      </button>
      {isOpen && (
        <div className="seo-analyzer-accordion__content">
          {children}
        </div>
      )}
    </div>
  );
};

/**
 * Result item component
 */
interface ResultItemProps {
  result: RuleResult;
  colorClass: string;
}

const ResultItem: React.FC<ResultItemProps> = ({ result, colorClass }) => (
  <div className={`seo-analyzer-result ${colorClass}`}>
    <div className="seo-analyzer-result__header">
      <span className="seo-analyzer-result__name">{result.name}</span>
      <span className="seo-analyzer-result__category">{result.category}</span>
    </div>
    <p className="seo-analyzer-result__message">{result.message}</p>
    {result.suggestions && result.suggestions.length > 0 && (
      <ul className="seo-analyzer-result__suggestions">
        {result.suggestions.map((suggestion, idx) => (
          <li key={idx}>{suggestion}</li>
        ))}
      </ul>
    )}
  </div>
);

/**
 * SEO Analyzer component
 */
export function SEOAnalyzer({
  score,
  results,
  loading = false,
}: SEOAnalyzerProps): React.ReactElement {
  // Calculate circumference for SVG gauge
  const radius = 50;
  const circumference = 2 * Math.PI * radius;
  const progressOffset = circumference * (1 - Math.min(Math.max(score, 0), 100) / 100);

  // Group results by status
  const groupedResults = useMemo(() => {
    const groups = {
      fail: [] as RuleResult[],
      warning: [] as RuleResult[],
      pass: [] as RuleResult[],
      skip: [] as RuleResult[],
    };

    results.forEach((result) => {
      if (groups[result.status]) {
        groups[result.status].push(result);
      }
    });

    return groups;
  }, [results]);

  // Score classification
  const scoreColorClass = useMemo(() => {
    if (score >= 80) return 'seo-analyzer--good';
    if (score >= 50) return 'seo-analyzer--warning';
    return 'seo-analyzer--bad';
  }, [score]);

  const scoreVerdict = useMemo(() => {
    if (loading) return 'Analyzing...';
    if (score >= 90) return 'Excellent! Your SEO is well optimized.';
    if (score >= 80) return 'Good! A few minor improvements could help.';
    if (score >= 60) return 'Fair. There are opportunities to improve.';
    if (score >= 40) return 'Needs Work. Several issues should be addressed.';
    return 'Poor. Major SEO improvements are needed.';
  }, [score, loading]);

  return (
    <div className="seo-analyzer">
      {/* Score Section */}
      <div className="seo-analyzer__score-section">
        <div className="seo-analyzer__gauge">
          <svg className="seo-analyzer__gauge-svg" viewBox="0 0 120 120">
            {/* Background circle */}
            <circle
              className="seo-analyzer__gauge-bg"
              cx="60"
              cy="60"
              r={radius}
              fill="none"
              strokeWidth="10"
            />
            {/* Progress circle */}
            <circle
              className={`seo-analyzer__gauge-progress ${scoreColorClass}`}
              cx="60"
              cy="60"
              r={radius}
              fill="none"
              strokeWidth="10"
              strokeLinecap="round"
              strokeDasharray={circumference}
              strokeDashoffset={progressOffset}
            />
          </svg>
          <div className="seo-analyzer__score-value">
            <span className={`seo-analyzer__score-number ${scoreColorClass}`}>
              {loading ? '...' : score}
            </span>
            <span className="seo-analyzer__score-label">SEO Score</span>
          </div>
        </div>
        <p className={`seo-analyzer__verdict ${scoreColorClass}`}>
          {scoreVerdict}
        </p>
      </div>

      {/* Loading State */}
      {loading ? (
        <div className="seo-analyzer__loading">
          {[1, 2, 3].map((i) => (
            <div key={i} className="seo-analyzer__skeleton" />
          ))}
        </div>
      ) : results.length > 0 ? (
        <div className="seo-analyzer__results">
          {/* Failed Rules */}
          {groupedResults.fail.length > 0 && (
            <Accordion
              title="Issues to Fix"
              count={groupedResults.fail.length}
              icon={<XCircleIcon className="seo-analyzer__icon" />}
              colorClass="seo-analyzer--fail"
              defaultOpen
            >
              {groupedResults.fail.map((result) => (
                <ResultItem
                  key={result.rule}
                  result={result}
                  colorClass="seo-analyzer--fail"
                />
              ))}
            </Accordion>
          )}

          {/* Warning Rules */}
          {groupedResults.warning.length > 0 && (
            <Accordion
              title="Warnings"
              count={groupedResults.warning.length}
              icon={<ExclamationTriangleIcon className="seo-analyzer__icon" />}
              colorClass="seo-analyzer--warning"
            >
              {groupedResults.warning.map((result) => (
                <ResultItem
                  key={result.rule}
                  result={result}
                  colorClass="seo-analyzer--warning"
                />
              ))}
            </Accordion>
          )}

          {/* Passed Rules */}
          {groupedResults.pass.length > 0 && (
            <Accordion
              title="Passed"
              count={groupedResults.pass.length}
              icon={<CheckCircleIcon className="seo-analyzer__icon" />}
              colorClass="seo-analyzer--pass"
            >
              {groupedResults.pass.map((result) => (
                <ResultItem
                  key={result.rule}
                  result={result}
                  colorClass="seo-analyzer--pass"
                />
              ))}
            </Accordion>
          )}

          {/* Skipped Rules */}
          {groupedResults.skip.length > 0 && (
            <Accordion
              title="Skipped"
              count={groupedResults.skip.length}
              icon={<XCircleIcon className="seo-analyzer__icon" />}
              colorClass="seo-analyzer--skip"
            >
              {groupedResults.skip.map((result) => (
                <ResultItem
                  key={result.rule}
                  result={result}
                  colorClass="seo-analyzer--skip"
                />
              ))}
            </Accordion>
          )}
        </div>
      ) : (
        /* Empty State */
        <div className="seo-analyzer__empty">
          <svg className="seo-analyzer__empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
          </svg>
          <p className="seo-analyzer__empty-text">No analysis results yet</p>
          <p className="seo-analyzer__empty-hint">Run an analysis to see SEO recommendations</p>
        </div>
      )}

      <style>{`
        .seo-analyzer {
          --seo-good: #10b981;
          --seo-warning: #f59e0b;
          --seo-bad: #ef4444;
          --seo-skip: #6b7280;
          --seo-gray-100: #f3f4f6;
          --seo-gray-200: #e5e7eb;
          --seo-gray-300: #d1d5db;
          --seo-gray-400: #9ca3af;
          --seo-gray-500: #6b7280;
          --seo-gray-600: #4b5563;
          --seo-gray-700: #374151;
          --seo-gray-800: #1f2937;
          --seo-radius: 0.5rem;
          font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .seo-analyzer--good { color: var(--seo-good); }
        .seo-analyzer--warning { color: var(--seo-warning); }
        .seo-analyzer--bad { color: var(--seo-bad); }
        .seo-analyzer--fail { color: var(--seo-bad); }
        .seo-analyzer--pass { color: var(--seo-good); }
        .seo-analyzer--skip { color: var(--seo-skip); }

        .seo-analyzer__score-section {
          display: flex;
          flex-direction: column;
          align-items: center;
          padding: 2rem;
          margin-bottom: 1.5rem;
          background: linear-gradient(135deg, var(--seo-gray-100) 0%, white 100%);
          border-radius: var(--seo-radius);
        }

        .seo-analyzer__gauge {
          position: relative;
          width: 140px;
          height: 140px;
        }

        .seo-analyzer__gauge-svg {
          transform: rotate(-90deg);
          width: 100%;
          height: 100%;
        }

        .seo-analyzer__gauge-bg {
          stroke: var(--seo-gray-200);
        }

        .seo-analyzer__gauge-progress {
          transition: stroke-dashoffset 1s ease-out, stroke 0.3s ease;
        }

        .seo-analyzer__gauge-progress.seo-analyzer--good { stroke: var(--seo-good); }
        .seo-analyzer__gauge-progress.seo-analyzer--warning { stroke: var(--seo-warning); }
        .seo-analyzer__gauge-progress.seo-analyzer--bad { stroke: var(--seo-bad); }

        .seo-analyzer__score-value {
          position: absolute;
          inset: 0;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
        }

        .seo-analyzer__score-number {
          font-size: 2.5rem;
          font-weight: 700;
          line-height: 1;
        }

        .seo-analyzer__score-label {
          font-size: 0.75rem;
          font-weight: 500;
          color: var(--seo-gray-500);
          text-transform: uppercase;
          letter-spacing: 0.05em;
          margin-top: 0.25rem;
        }

        .seo-analyzer__verdict {
          margin-top: 1rem;
          font-size: 0.9375rem;
          font-weight: 500;
          text-align: center;
        }

        .seo-analyzer__loading {
          display: flex;
          flex-direction: column;
          gap: 0.75rem;
        }

        .seo-analyzer__skeleton {
          height: 60px;
          background: linear-gradient(90deg, var(--seo-gray-100) 25%, var(--seo-gray-200) 50%, var(--seo-gray-100) 75%);
          background-size: 200% 100%;
          border-radius: var(--seo-radius);
          animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
          0% { background-position: 200% 0; }
          100% { background-position: -200% 0; }
        }

        .seo-analyzer__results {
          display: flex;
          flex-direction: column;
          gap: 0.75rem;
        }

        .seo-analyzer-accordion {
          border: 1px solid var(--seo-gray-200);
          border-radius: var(--seo-radius);
          overflow: hidden;
        }

        .seo-analyzer-accordion__header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          width: 100%;
          padding: 0.875rem 1rem;
          font-size: 0.9375rem;
          font-weight: 600;
          background: transparent;
          border: none;
          cursor: pointer;
          transition: background-color 150ms ease;
        }

        .seo-analyzer-accordion__header:hover {
          background: var(--seo-gray-100);
        }

        .seo-analyzer-accordion__title {
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }

        .seo-analyzer__icon {
          width: 1.25rem;
          height: 1.25rem;
        }

        .seo-analyzer-accordion__meta {
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }

        .seo-analyzer-accordion__count {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 1.5rem;
          height: 1.5rem;
          padding: 0 0.5rem;
          font-size: 0.75rem;
          font-weight: 600;
          border-radius: 9999px;
          background: currentColor;
          color: white;
        }

        .seo-analyzer-accordion__count.seo-analyzer--fail { background: var(--seo-bad); }
        .seo-analyzer-accordion__count.seo-analyzer--warning { background: var(--seo-warning); }
        .seo-analyzer-accordion__count.seo-analyzer--pass { background: var(--seo-good); }
        .seo-analyzer-accordion__count.seo-analyzer--skip { background: var(--seo-skip); }

        .seo-analyzer-accordion__chevron {
          width: 1.25rem;
          height: 1.25rem;
          color: var(--seo-gray-400);
          transition: transform 200ms ease;
        }

        .seo-analyzer-accordion__chevron--open {
          transform: rotate(180deg);
        }

        .seo-analyzer-accordion__content {
          border-top: 1px solid var(--seo-gray-200);
        }

        .seo-analyzer-result {
          padding: 1rem;
          border-bottom: 1px solid var(--seo-gray-100);
        }

        .seo-analyzer-result:last-child {
          border-bottom: none;
        }

        .seo-analyzer-result.seo-analyzer--fail { border-left: 3px solid var(--seo-bad); }
        .seo-analyzer-result.seo-analyzer--warning { border-left: 3px solid var(--seo-warning); }
        .seo-analyzer-result.seo-analyzer--pass { border-left: 3px solid var(--seo-good); }
        .seo-analyzer-result.seo-analyzer--skip { border-left: 3px solid var(--seo-skip); }

        .seo-analyzer-result__header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.75rem;
          margin-bottom: 0.375rem;
        }

        .seo-analyzer-result__name {
          font-size: 0.875rem;
          font-weight: 600;
          color: var(--seo-gray-800);
        }

        .seo-analyzer-result__category {
          font-size: 0.6875rem;
          font-weight: 500;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          color: var(--seo-gray-400);
          padding: 0.125rem 0.5rem;
          background: var(--seo-gray-100);
          border-radius: 9999px;
        }

        .seo-analyzer-result__message {
          font-size: 0.8125rem;
          color: var(--seo-gray-600);
          line-height: 1.5;
          margin: 0;
        }

        .seo-analyzer-result__suggestions {
          margin: 0.75rem 0 0;
          padding-left: 1.25rem;
          font-size: 0.8125rem;
          color: var(--seo-gray-500);
          list-style-type: disc;
        }

        .seo-analyzer-result__suggestions li {
          margin-bottom: 0.25rem;
        }

        .seo-analyzer__empty {
          display: flex;
          flex-direction: column;
          align-items: center;
          padding: 3rem 1.5rem;
          text-align: center;
        }

        .seo-analyzer__empty-icon {
          width: 3rem;
          height: 3rem;
          color: var(--seo-gray-300);
          margin-bottom: 1rem;
        }

        .seo-analyzer__empty-text {
          font-size: 0.9375rem;
          font-weight: 600;
          color: var(--seo-gray-600);
          margin: 0;
        }

        .seo-analyzer__empty-hint {
          font-size: 0.8125rem;
          color: var(--seo-gray-400);
          margin: 0.5rem 0 0;
        }
      `}</style>
    </div>
  );
}

export default SEOAnalyzer;
