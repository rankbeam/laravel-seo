/**
 * Keyword Manager Component for React
 *
 * Manages focus keywords with synonyms, add/remove operations, and primary selection.
 *
 * @package fibonoir/laravel-seo
 */

import React, { useState, useCallback } from 'react';
import type { Keyword, KeywordManagerProps } from '../types/seo';

// Icons as inline SVGs
const StarIcon: React.FC<{ className?: string; filled?: boolean }> = ({ className, filled }) => (
  <svg className={className} viewBox="0 0 20 20" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={filled ? 0 : 1.5}>
    <path fillRule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clipRule="evenodd" />
  </svg>
);

const TrashIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg className={className} viewBox="0 0 20 20" fill="currentColor">
    <path fillRule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clipRule="evenodd" />
  </svg>
);

const XMarkIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg className={className} viewBox="0 0 20 20" fill="currentColor">
    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
  </svg>
);

const PlusIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg className={className} viewBox="0 0 20 20" fill="currentColor">
    <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
  </svg>
);

/**
 * Generate a unique ID for keywords
 */
function generateId(): string {
  return `kw-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
}

/**
 * Keyword Manager component
 */
export function KeywordManager({
  value,
  onChange,
  maxKeywords = 5,
  maxSynonyms = 5,
}: KeywordManagerProps): React.ReactElement {
  const [synonymInputs, setSynonymInputs] = useState<Record<number, string>>({});

  // Add a new keyword
  const handleAddKeyword = useCallback(() => {
    if (value.length >= maxKeywords) return;

    const newKeyword: Keyword = {
      id: generateId(),
      keyword: '',
      isPrimary: value.length === 0, // First keyword is primary by default
      synonyms: [],
    };

    onChange([...value, newKeyword]);
  }, [value, onChange, maxKeywords]);

  // Remove a keyword
  const handleRemoveKeyword = useCallback((index: number) => {
    const updated = [...value];
    const wasRemoved = updated.splice(index, 1)[0];

    // If removed keyword was primary, make first remaining keyword primary
    if (wasRemoved.isPrimary && updated.length > 0) {
      updated[0] = { ...updated[0], isPrimary: true };
    }

    // Clean up synonym input
    setSynonymInputs((prev) => {
      const next = { ...prev };
      delete next[index];
      return next;
    });

    onChange(updated);
  }, [value, onChange]);

  // Toggle primary keyword
  const handleTogglePrimary = useCallback((index: number) => {
    const updated = value.map((kw, i) => ({
      ...kw,
      isPrimary: i === index,
    }));
    onChange(updated);
  }, [value, onChange]);

  // Update keyword text
  const handleKeywordChange = useCallback((index: number, text: string) => {
    const updated = [...value];
    updated[index] = { ...updated[index], keyword: text };
    onChange(updated);
  }, [value, onChange]);

  // Add synonym to keyword
  const handleAddSynonym = useCallback((keywordIndex: number) => {
    const synonymText = synonymInputs[keywordIndex]?.trim();
    if (!synonymText) return;

    const keyword = value[keywordIndex];
    if (!keyword || keyword.synonyms.length >= maxSynonyms) return;

    // Check for duplicates
    if (keyword.synonyms.some((s) => s.toLowerCase() === synonymText.toLowerCase())) {
      setSynonymInputs((prev) => ({ ...prev, [keywordIndex]: '' }));
      return;
    }

    const updated = [...value];
    updated[keywordIndex] = {
      ...updated[keywordIndex],
      synonyms: [...updated[keywordIndex].synonyms, synonymText],
    };

    setSynonymInputs((prev) => ({ ...prev, [keywordIndex]: '' }));
    onChange(updated);
  }, [value, onChange, synonymInputs, maxSynonyms]);

  // Remove synonym from keyword
  const handleRemoveSynonym = useCallback((keywordIndex: number, synonymIndex: number) => {
    const updated = [...value];
    updated[keywordIndex] = {
      ...updated[keywordIndex],
      synonyms: updated[keywordIndex].synonyms.filter((_, i) => i !== synonymIndex),
    };
    onChange(updated);
  }, [value, onChange]);

  // Handle synonym input change
  const handleSynonymInputChange = useCallback((keywordIndex: number, text: string) => {
    setSynonymInputs((prev) => ({ ...prev, [keywordIndex]: text }));
  }, []);

  // Handle synonym input key down
  const handleSynonymKeyDown = useCallback((e: React.KeyboardEvent, keywordIndex: number) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      handleAddSynonym(keywordIndex);
    }
  }, [handleAddSynonym]);

  return (
    <div className="keyword-manager">
      {/* Keywords List */}
      <div className="keyword-manager__list">
        {value.map((keyword, index) => (
          <div
            key={keyword.id}
            className={`keyword-manager__item ${keyword.isPrimary ? 'keyword-manager__item--primary' : ''}`}
          >
            <div className="keyword-manager__header">
              <div className="keyword-manager__main">
                <button
                  type="button"
                  className={`keyword-manager__star ${keyword.isPrimary ? 'keyword-manager__star--active' : ''}`}
                  onClick={() => handleTogglePrimary(index)}
                  title={keyword.isPrimary ? 'Primary keyword' : 'Set as primary'}
                >
                  <StarIcon className="keyword-manager__star-icon" filled={keyword.isPrimary} />
                </button>
                <input
                  type="text"
                  value={keyword.keyword}
                  onChange={(e) => handleKeywordChange(index, e.target.value)}
                  placeholder="Enter keyword..."
                  className="keyword-manager__input"
                />
              </div>
              <button
                type="button"
                className="keyword-manager__remove"
                onClick={() => handleRemoveKeyword(index)}
                title="Remove keyword"
              >
                <TrashIcon className="keyword-manager__remove-icon" />
              </button>
            </div>

            {/* Synonyms Section */}
            <div className="keyword-manager__synonyms">
              <div className="keyword-manager__synonyms-label">
                <span>Synonyms</span>
                <span className="keyword-manager__synonyms-count">
                  {keyword.synonyms.length}/{maxSynonyms}
                </span>
              </div>
              <div className="keyword-manager__chips">
                {keyword.synonyms.map((synonym, synIndex) => (
                  <span key={`${keyword.id}-syn-${synIndex}`} className="keyword-manager__chip">
                    {synonym}
                    <button
                      type="button"
                      className="keyword-manager__chip-remove"
                      onClick={() => handleRemoveSynonym(index, synIndex)}
                    >
                      <XMarkIcon className="keyword-manager__chip-icon" />
                    </button>
                  </span>
                ))}
                {keyword.synonyms.length < maxSynonyms && (
                  <input
                    type="text"
                    value={synonymInputs[index] || ''}
                    onChange={(e) => handleSynonymInputChange(index, e.target.value)}
                    onKeyDown={(e) => handleSynonymKeyDown(e, index)}
                    onBlur={() => handleAddSynonym(index)}
                    placeholder="Add synonym..."
                    className="keyword-manager__synonym-input"
                  />
                )}
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Add Keyword Button */}
      {value.length < maxKeywords && (
        <button
          type="button"
          className="keyword-manager__add"
          onClick={handleAddKeyword}
        >
          <PlusIcon className="keyword-manager__add-icon" />
          Add Keyword
          <span className="keyword-manager__add-count">{value.length}/{maxKeywords}</span>
        </button>
      )}

      {/* Empty State */}
      {value.length === 0 && (
        <div className="keyword-manager__empty">
          <p>No focus keywords added yet.</p>
          <p className="keyword-manager__empty-hint">
            Focus keywords help analyze how well your content is optimized for specific terms.
          </p>
        </div>
      )}

      <style>{`
        .keyword-manager {
          --km-primary: #4f46e5;
          --km-primary-light: #eef2ff;
          --km-star: #f59e0b;
          --km-danger: #ef4444;
          --km-gray-50: #f9fafb;
          --km-gray-100: #f3f4f6;
          --km-gray-200: #e5e7eb;
          --km-gray-300: #d1d5db;
          --km-gray-400: #9ca3af;
          --km-gray-500: #6b7280;
          --km-gray-600: #4b5563;
          --km-gray-700: #374151;
          --km-gray-800: #1f2937;
          --km-radius: 0.5rem;
          --km-transition: 150ms ease;
          font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .keyword-manager__list {
          display: flex;
          flex-direction: column;
          gap: 0.75rem;
        }

        .keyword-manager__item {
          padding: 1rem;
          background: var(--km-gray-50);
          border: 1px solid var(--km-gray-200);
          border-radius: var(--km-radius);
          transition: border-color var(--km-transition), box-shadow var(--km-transition);
        }

        .keyword-manager__item:hover {
          border-color: var(--km-gray-300);
        }

        .keyword-manager__item--primary {
          border-color: var(--km-primary);
          background: var(--km-primary-light);
        }

        .keyword-manager__header {
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }

        .keyword-manager__main {
          flex: 1;
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }

        .keyword-manager__star {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 2rem;
          height: 2rem;
          padding: 0;
          background: transparent;
          border: none;
          border-radius: var(--km-radius);
          color: var(--km-gray-300);
          cursor: pointer;
          transition: color var(--km-transition), transform var(--km-transition);
        }

        .keyword-manager__star:hover {
          color: var(--km-star);
          transform: scale(1.1);
        }

        .keyword-manager__star--active {
          color: var(--km-star);
        }

        .keyword-manager__star-icon {
          width: 1.25rem;
          height: 1.25rem;
        }

        .keyword-manager__input {
          flex: 1;
          padding: 0.5rem 0.75rem;
          font-size: 0.9375rem;
          color: var(--km-gray-800);
          background: white;
          border: 1px solid var(--km-gray-200);
          border-radius: var(--km-radius);
          transition: border-color var(--km-transition), box-shadow var(--km-transition);
        }

        .keyword-manager__input:focus {
          outline: none;
          border-color: var(--km-primary);
          box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .keyword-manager__remove {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 2rem;
          height: 2rem;
          padding: 0;
          background: transparent;
          border: none;
          border-radius: var(--km-radius);
          color: var(--km-gray-400);
          cursor: pointer;
          transition: color var(--km-transition), background-color var(--km-transition);
        }

        .keyword-manager__remove:hover {
          color: var(--km-danger);
          background: rgba(239, 68, 68, 0.1);
        }

        .keyword-manager__remove-icon {
          width: 1.125rem;
          height: 1.125rem;
        }

        .keyword-manager__synonyms {
          margin-top: 0.75rem;
          padding-top: 0.75rem;
          border-top: 1px solid var(--km-gray-200);
        }

        .keyword-manager__synonyms-label {
          display: flex;
          justify-content: space-between;
          font-size: 0.75rem;
          font-weight: 500;
          color: var(--km-gray-500);
          text-transform: uppercase;
          letter-spacing: 0.05em;
          margin-bottom: 0.5rem;
        }

        .keyword-manager__synonyms-count {
          font-weight: 400;
          color: var(--km-gray-400);
        }

        .keyword-manager__chips {
          display: flex;
          flex-wrap: wrap;
          gap: 0.375rem;
          align-items: center;
        }

        .keyword-manager__chip {
          display: inline-flex;
          align-items: center;
          gap: 0.25rem;
          padding: 0.25rem 0.5rem;
          font-size: 0.8125rem;
          color: var(--km-gray-700);
          background: white;
          border: 1px solid var(--km-gray-200);
          border-radius: 9999px;
          animation: chipIn 200ms ease;
        }

        @keyframes chipIn {
          from { opacity: 0; transform: scale(0.8); }
          to { opacity: 1; transform: scale(1); }
        }

        .keyword-manager__chip-remove {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 1rem;
          height: 1rem;
          padding: 0;
          background: transparent;
          border: none;
          border-radius: 50%;
          color: var(--km-gray-400);
          cursor: pointer;
          transition: color var(--km-transition), background-color var(--km-transition);
        }

        .keyword-manager__chip-remove:hover {
          color: var(--km-danger);
          background: rgba(239, 68, 68, 0.1);
        }

        .keyword-manager__chip-icon {
          width: 0.75rem;
          height: 0.75rem;
        }

        .keyword-manager__synonym-input {
          flex: 1;
          min-width: 100px;
          padding: 0.25rem 0.5rem;
          font-size: 0.8125rem;
          color: var(--km-gray-800);
          background: transparent;
          border: 1px dashed var(--km-gray-300);
          border-radius: 9999px;
          transition: border-color var(--km-transition), background-color var(--km-transition);
        }

        .keyword-manager__synonym-input:focus {
          outline: none;
          border-color: var(--km-primary);
          border-style: solid;
          background: white;
        }

        .keyword-manager__synonym-input::placeholder {
          color: var(--km-gray-400);
        }

        .keyword-manager__add {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 0.5rem;
          width: 100%;
          padding: 0.75rem 1rem;
          font-size: 0.875rem;
          font-weight: 500;
          color: var(--km-gray-600);
          background: white;
          border: 2px dashed var(--km-gray-300);
          border-radius: var(--km-radius);
          cursor: pointer;
          transition: all var(--km-transition);
          margin-top: 0.75rem;
        }

        .keyword-manager__add:hover {
          color: var(--km-primary);
          border-color: var(--km-primary);
          background: var(--km-primary-light);
        }

        .keyword-manager__add-icon {
          width: 1.125rem;
          height: 1.125rem;
        }

        .keyword-manager__add-count {
          font-size: 0.75rem;
          color: var(--km-gray-400);
          margin-left: auto;
        }

        .keyword-manager__empty {
          padding: 1.5rem;
          text-align: center;
          color: var(--km-gray-500);
          font-size: 0.875rem;
        }

        .keyword-manager__empty p {
          margin: 0;
        }

        .keyword-manager__empty-hint {
          font-size: 0.8125rem;
          color: var(--km-gray-400);
          margin-top: 0.5rem !important;
        }
      `}</style>
    </div>
  );
}

export default KeywordManager;
