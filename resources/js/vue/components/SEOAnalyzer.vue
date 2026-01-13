<template>
  <div class="seo-analyzer">
    <!-- Score Gauge -->
    <div class="seo-score-section">
      <div class="seo-score-gauge">
        <svg class="seo-gauge" viewBox="0 0 120 120">
          <!-- Background circle -->
          <circle
            class="seo-gauge__bg"
            cx="60"
            cy="60"
            r="50"
            fill="none"
            stroke-width="10"
          />
          <!-- Progress circle -->
          <circle
            class="seo-gauge__progress"
            :class="scoreColorClass"
            cx="60"
            cy="60"
            r="50"
            fill="none"
            stroke-width="10"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="progressOffset"
          />
        </svg>
        <div class="seo-score-value">
          <span class="seo-score-number" :class="scoreColorClass">
            {{ loading ? '...' : score }}
          </span>
          <span class="seo-score-label">SEO Score</span>
        </div>
      </div>
      <p class="seo-score-verdict" :class="scoreColorClass">
        {{ scoreVerdict }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="seo-analyzer-loading">
      <div class="seo-loading-skeleton" v-for="i in 3" :key="i" />
    </div>

    <!-- Results Sections -->
    <div v-else-if="results.length > 0" class="seo-results">
      <!-- Failed Rules -->
      <details v-if="failedResults.length > 0" class="seo-results-group" open>
        <summary class="seo-results-header seo-results-header--fail">
          <div class="seo-results-header__title">
            <svg class="seo-results-icon" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
            </svg>
            <span>Issues to Fix</span>
          </div>
          <span class="seo-results-count">{{ failedResults.length }}</span>
        </summary>
        <div class="seo-results-content">
          <div
            v-for="result in failedResults"
            :key="result.rule"
            class="seo-result-item seo-result-item--fail"
          >
            <div class="seo-result-header">
              <span class="seo-result-name">{{ result.name }}</span>
              <span class="seo-result-category">{{ result.category }}</span>
            </div>
            <p class="seo-result-message">{{ result.message }}</p>
            <ul v-if="result.suggestions?.length" class="seo-result-suggestions">
              <li v-for="(suggestion, idx) in result.suggestions" :key="idx">
                {{ suggestion }}
              </li>
            </ul>
          </div>
        </div>
      </details>

      <!-- Warning Rules -->
      <details v-if="warningResults.length > 0" class="seo-results-group">
        <summary class="seo-results-header seo-results-header--warning">
          <div class="seo-results-header__title">
            <svg class="seo-results-icon" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
            <span>Warnings</span>
          </div>
          <span class="seo-results-count">{{ warningResults.length }}</span>
        </summary>
        <div class="seo-results-content">
          <div
            v-for="result in warningResults"
            :key="result.rule"
            class="seo-result-item seo-result-item--warning"
          >
            <div class="seo-result-header">
              <span class="seo-result-name">{{ result.name }}</span>
              <span class="seo-result-category">{{ result.category }}</span>
            </div>
            <p class="seo-result-message">{{ result.message }}</p>
            <ul v-if="result.suggestions?.length" class="seo-result-suggestions">
              <li v-for="(suggestion, idx) in result.suggestions" :key="idx">
                {{ suggestion }}
              </li>
            </ul>
          </div>
        </div>
      </details>

      <!-- Passed Rules -->
      <details v-if="passedResults.length > 0" class="seo-results-group">
        <summary class="seo-results-header seo-results-header--pass">
          <div class="seo-results-header__title">
            <svg class="seo-results-icon" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
            <span>Passed</span>
          </div>
          <span class="seo-results-count">{{ passedResults.length }}</span>
        </summary>
        <div class="seo-results-content">
          <div
            v-for="result in passedResults"
            :key="result.rule"
            class="seo-result-item seo-result-item--pass"
          >
            <div class="seo-result-header">
              <span class="seo-result-name">{{ result.name }}</span>
              <span class="seo-result-category">{{ result.category }}</span>
            </div>
            <p class="seo-result-message">{{ result.message }}</p>
          </div>
        </div>
      </details>

      <!-- Skipped Rules -->
      <details v-if="skippedResults.length > 0" class="seo-results-group">
        <summary class="seo-results-header seo-results-header--skip">
          <div class="seo-results-header__title">
            <svg class="seo-results-icon" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 10-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
            </svg>
            <span>Skipped</span>
          </div>
          <span class="seo-results-count">{{ skippedResults.length }}</span>
        </summary>
        <div class="seo-results-content">
          <div
            v-for="result in skippedResults"
            :key="result.rule"
            class="seo-result-item seo-result-item--skip"
          >
            <div class="seo-result-header">
              <span class="seo-result-name">{{ result.name }}</span>
              <span class="seo-result-category">{{ result.category }}</span>
            </div>
            <p class="seo-result-message">{{ result.message }}</p>
          </div>
        </div>
      </details>
    </div>

    <!-- Empty State -->
    <div v-else class="seo-analyzer-empty">
      <svg class="seo-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
      </svg>
      <p class="seo-empty-text">No analysis results yet</p>
      <p class="seo-empty-hint">Run an analysis to see SEO recommendations</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { RuleResult, SEOAnalyzerProps } from '../types/seo';

const props = withDefaults(defineProps<SEOAnalyzerProps>(), {
  score: 0,
  results: () => [],
  loading: false,
});

// SVG circle math
const circumference = 2 * Math.PI * 50; // radius = 50

const progressOffset = computed(() => {
  const progress = Math.min(Math.max(props.score, 0), 100) / 100;
  return circumference * (1 - progress);
});

// Score classification
const scoreColorClass = computed(() => {
  if (props.score >= 80) return 'seo-color--good';
  if (props.score >= 50) return 'seo-color--warning';
  return 'seo-color--bad';
});

const scoreVerdict = computed(() => {
  if (props.loading) return 'Analyzing...';
  if (props.score >= 90) return 'Excellent! Your SEO is well optimized.';
  if (props.score >= 80) return 'Good! A few minor improvements could help.';
  if (props.score >= 60) return 'Fair. There are opportunities to improve.';
  if (props.score >= 40) return 'Needs Work. Several issues should be addressed.';
  return 'Poor. Major SEO improvements are needed.';
});

// Filtered results
const passedResults = computed(() =>
  props.results.filter((r) => r.status === 'pass')
);

const warningResults = computed(() =>
  props.results.filter((r) => r.status === 'warning')
);

const failedResults = computed(() =>
  props.results.filter((r) => r.status === 'fail')
);

const skippedResults = computed(() =>
  props.results.filter((r) => r.status === 'skip')
);
</script>

<style scoped>
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

/* Color Classes */
.seo-color--good { color: var(--seo-good); }
.seo-color--warning { color: var(--seo-warning); }
.seo-color--bad { color: var(--seo-bad); }

/* Score Section */
.seo-score-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2rem;
  margin-bottom: 1.5rem;
  background: linear-gradient(135deg, var(--seo-gray-100) 0%, white 100%);
  border-radius: var(--seo-radius);
}

.seo-score-gauge {
  position: relative;
  width: 140px;
  height: 140px;
}

.seo-gauge {
  transform: rotate(-90deg);
  width: 100%;
  height: 100%;
}

.seo-gauge__bg {
  stroke: var(--seo-gray-200);
}

.seo-gauge__progress {
  transition: stroke-dashoffset 1s ease-out, stroke 0.3s ease;
}

.seo-gauge__progress.seo-color--good { stroke: var(--seo-good); }
.seo-gauge__progress.seo-color--warning { stroke: var(--seo-warning); }
.seo-gauge__progress.seo-color--bad { stroke: var(--seo-bad); }

.seo-score-value {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.seo-score-number {
  font-size: 2.5rem;
  font-weight: 700;
  line-height: 1;
}

.seo-score-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--seo-gray-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.25rem;
}

.seo-score-verdict {
  margin-top: 1rem;
  font-size: 0.9375rem;
  font-weight: 500;
  text-align: center;
}

/* Loading State */
.seo-analyzer-loading {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.seo-loading-skeleton {
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

/* Results Groups */
.seo-results {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.seo-results-group {
  border: 1px solid var(--seo-gray-200);
  border-radius: var(--seo-radius);
  overflow: hidden;
}

.seo-results-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.875rem 1rem;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  user-select: none;
  list-style: none;
  transition: background-color 150ms ease;
}

.seo-results-header::-webkit-details-marker {
  display: none;
}

.seo-results-header:hover {
  background: var(--seo-gray-100);
}

.seo-results-header__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.seo-results-icon {
  width: 1.25rem;
  height: 1.25rem;
}

.seo-results-header--fail { color: var(--seo-bad); }
.seo-results-header--warning { color: var(--seo-warning); }
.seo-results-header--pass { color: var(--seo-good); }
.seo-results-header--skip { color: var(--seo-skip); }

.seo-results-count {
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

.seo-results-header--fail .seo-results-count { background: var(--seo-bad); }
.seo-results-header--warning .seo-results-count { background: var(--seo-warning); }
.seo-results-header--pass .seo-results-count { background: var(--seo-good); }
.seo-results-header--skip .seo-results-count { background: var(--seo-skip); }

.seo-results-content {
  border-top: 1px solid var(--seo-gray-200);
}

/* Result Items */
.seo-result-item {
  padding: 1rem;
  border-bottom: 1px solid var(--seo-gray-100);
}

.seo-result-item:last-child {
  border-bottom: none;
}

.seo-result-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.375rem;
}

.seo-result-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--seo-gray-800);
}

.seo-result-category {
  font-size: 0.6875rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--seo-gray-400);
  padding: 0.125rem 0.5rem;
  background: var(--seo-gray-100);
  border-radius: 9999px;
}

.seo-result-message {
  font-size: 0.8125rem;
  color: var(--seo-gray-600);
  line-height: 1.5;
  margin: 0;
}

.seo-result-suggestions {
  margin: 0.75rem 0 0;
  padding-left: 1.25rem;
  font-size: 0.8125rem;
  color: var(--seo-gray-500);
  list-style-type: disc;
}

.seo-result-suggestions li {
  margin-bottom: 0.25rem;
}

.seo-result-suggestions li:last-child {
  margin-bottom: 0;
}

/* Left border accent */
.seo-result-item--fail { border-left: 3px solid var(--seo-bad); }
.seo-result-item--warning { border-left: 3px solid var(--seo-warning); }
.seo-result-item--pass { border-left: 3px solid var(--seo-good); }
.seo-result-item--skip { border-left: 3px solid var(--seo-skip); }

/* Empty State */
.seo-analyzer-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 3rem 1.5rem;
  text-align: center;
}

.seo-empty-icon {
  width: 3rem;
  height: 3rem;
  color: var(--seo-gray-300);
  margin-bottom: 1rem;
}

.seo-empty-text {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--seo-gray-600);
  margin: 0;
}

.seo-empty-hint {
  font-size: 0.8125rem;
  color: var(--seo-gray-400);
  margin: 0.5rem 0 0;
}
</style>
