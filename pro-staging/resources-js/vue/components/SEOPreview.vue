<template>
  <div class="seo-preview">
    <!-- Preview Type Tabs -->
    <div class="preview-tabs">
      <button
        v-for="tab in previewTabs"
        :key="tab.id"
        type="button"
        :class="['preview-tab', { 'preview-tab--active': activePreview === tab.id }]"
        @click="activePreview = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Google SERP Preview -->
    <div v-show="activePreview === 'google'" class="preview-google">
      <div class="google-result">
        <div class="google-favicon">
          <svg viewBox="0 0 16 16" fill="currentColor">
            <circle cx="8" cy="8" r="7" fill="#e5e7eb" />
            <path d="M8 4a4 4 0 100 8 4 4 0 000-8z" fill="#9ca3af" />
          </svg>
        </div>
        <div class="google-content">
          <div class="google-breadcrumb">
            <span class="google-url">{{ displayUrl }}</span>
            <span class="google-chevron">›</span>
            <span class="google-path">{{ urlPath }}</span>
          </div>
          <h3 class="google-title">{{ truncatedTitle }}</h3>
          <p class="google-description">{{ truncatedDescription }}</p>
        </div>
      </div>
      <p class="preview-note">
        This is an approximation of how your page may appear in Google search results.
      </p>
    </div>

    <!-- Open Graph Preview -->
    <div v-show="activePreview === 'og'" class="preview-og">
      <div class="og-card">
        <div v-if="ogImage" class="og-image">
          <img :src="ogImage" :alt="title" @error="handleImageError" />
        </div>
        <div v-else class="og-image og-image--placeholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
          </svg>
          <span>No image set</span>
        </div>
        <div class="og-content">
          <p class="og-site">{{ siteName || displayDomain }}</p>
          <h3 class="og-title">{{ truncatedOgTitle }}</h3>
          <p class="og-description">{{ truncatedOgDescription }}</p>
        </div>
      </div>
      <p class="preview-note">
        Preview of how your page may appear when shared on Facebook and LinkedIn.
      </p>
    </div>

    <!-- Twitter Card Preview -->
    <div v-show="activePreview === 'twitter'" class="preview-twitter">
      <div class="twitter-card">
        <div v-if="ogImage" class="twitter-image">
          <img :src="ogImage" :alt="title" @error="handleImageError" />
        </div>
        <div v-else class="twitter-image twitter-image--placeholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
          </svg>
        </div>
        <div class="twitter-content">
          <h3 class="twitter-title">{{ truncatedTwitterTitle }}</h3>
          <p class="twitter-description">{{ truncatedTwitterDescription }}</p>
          <p class="twitter-domain">
            <svg viewBox="0 0 16 16" fill="currentColor">
              <path d="M8 0a8 8 0 100 16A8 8 0 008 0zm0 14.5a6.5 6.5 0 110-13 6.5 6.5 0 010 13z" opacity="0.5" />
            </svg>
            {{ displayDomain }}
          </p>
        </div>
      </div>
      <p class="preview-note">
        Preview of how your page may appear when shared on X (Twitter).
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import type { SEOPreviewProps } from '../types/seo';

const props = withDefaults(defineProps<SEOPreviewProps>(), {
  title: '',
  description: '',
  url: '',
  siteName: '',
  ogImage: '',
});

type PreviewType = 'google' | 'og' | 'twitter';

const previewTabs: { id: PreviewType; label: string }[] = [
  { id: 'google', label: 'Google' },
  { id: 'og', label: 'Facebook/LinkedIn' },
  { id: 'twitter', label: 'X (Twitter)' },
];

const activePreview = ref<PreviewType>('google');

// URL parsing
const displayUrl = computed(() => {
  try {
    const urlObj = new URL(props.url);
    return urlObj.origin;
  } catch {
    return props.url;
  }
});

const displayDomain = computed(() => {
  try {
    const urlObj = new URL(props.url);
    return urlObj.hostname;
  } catch {
    return props.url;
  }
});

const urlPath = computed(() => {
  try {
    const urlObj = new URL(props.url);
    const path = urlObj.pathname;
    return path === '/' ? '' : path.substring(1);
  } catch {
    return '';
  }
});

// Title truncation (Google shows ~55-60 chars)
const truncatedTitle = computed(() => {
  const maxLength = 60;
  if (props.title.length <= maxLength) return props.title;
  return props.title.substring(0, maxLength - 3) + '...';
});

// Description truncation (Google shows ~155-160 chars)
const truncatedDescription = computed(() => {
  const maxLength = 160;
  if (props.description.length <= maxLength) return props.description;
  return props.description.substring(0, maxLength - 3) + '...';
});

// OG specific truncation
const truncatedOgTitle = computed(() => {
  const maxLength = 65;
  if (props.title.length <= maxLength) return props.title;
  return props.title.substring(0, maxLength - 3) + '...';
});

const truncatedOgDescription = computed(() => {
  const maxLength = 155;
  if (props.description.length <= maxLength) return props.description;
  return props.description.substring(0, maxLength - 3) + '...';
});

// Twitter specific truncation
const truncatedTwitterTitle = computed(() => {
  const maxLength = 70;
  if (props.title.length <= maxLength) return props.title;
  return props.title.substring(0, maxLength - 3) + '...';
});

const truncatedTwitterDescription = computed(() => {
  const maxLength = 200;
  if (props.description.length <= maxLength) return props.description;
  return props.description.substring(0, maxLength - 3) + '...';
});

// Image error handler
function handleImageError(event: Event): void {
  const img = event.target as HTMLImageElement;
  img.style.display = 'none';
}
</script>

<style scoped>
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

/* Preview Tabs */
.preview-tabs {
  display: flex;
  gap: 0.25rem;
  margin-bottom: 1rem;
  padding: 0.25rem;
  background: var(--preview-gray-100);
  border-radius: var(--preview-radius);
}

.preview-tab {
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

.preview-tab:hover {
  color: var(--preview-gray-800);
}

.preview-tab--active {
  color: var(--preview-gray-800);
  background: white;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

/* Google Preview */
.preview-google {
  animation: fadeIn 200ms ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.google-result {
  display: flex;
  gap: 0.875rem;
  padding: 1rem;
  background: white;
  border: 1px solid var(--preview-gray-200);
  border-radius: var(--preview-radius);
}

.google-favicon {
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
}

.google-favicon svg {
  width: 100%;
  height: 100%;
}

.google-content {
  min-width: 0;
}

.google-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: var(--preview-gray-500);
  margin-bottom: 0.25rem;
}

.google-url {
  color: var(--preview-gray-700);
}

.google-chevron {
  color: var(--preview-gray-400);
}

.google-path {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.google-title {
  font-family: Arial, sans-serif;
  font-size: 1.125rem;
  font-weight: 400;
  line-height: 1.3;
  color: var(--preview-google-blue);
  margin: 0 0 0.375rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.google-title:hover {
  text-decoration: underline;
}

.google-description {
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

/* Open Graph Preview */
.preview-og {
  animation: fadeIn 200ms ease;
}

.og-card {
  overflow: hidden;
  background: var(--preview-gray-50);
  border: 1px solid var(--preview-gray-200);
  border-radius: var(--preview-radius);
}

.og-image {
  aspect-ratio: 1.91 / 1;
  background: var(--preview-gray-200);
  overflow: hidden;
}

.og-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.og-image--placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: var(--preview-gray-400);
  font-size: 0.8125rem;
}

.og-image--placeholder svg {
  width: 2.5rem;
  height: 2.5rem;
}

.og-content {
  padding: 0.875rem 1rem;
  border-top: 1px solid var(--preview-gray-200);
}

.og-site {
  font-size: 0.6875rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--preview-gray-500);
  margin: 0 0 0.375rem;
}

.og-title {
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

.og-description {
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--preview-gray-500);
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Twitter Preview */
.preview-twitter {
  animation: fadeIn 200ms ease;
}

.twitter-card {
  overflow: hidden;
  background: white;
  border: 1px solid var(--preview-gray-200);
  border-radius: var(--preview-radius);
}

.twitter-image {
  aspect-ratio: 2 / 1;
  background: var(--preview-gray-200);
  overflow: hidden;
}

.twitter-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.twitter-image--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--preview-gray-400);
}

.twitter-image--placeholder svg {
  width: 3rem;
  height: 3rem;
}

.twitter-content {
  padding: 0.875rem 1rem;
}

.twitter-title {
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

.twitter-description {
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--preview-gray-500);
  margin: 0 0 0.5rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.twitter-domain {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: var(--preview-gray-400);
  margin: 0;
}

.twitter-domain svg {
  width: 0.875rem;
  height: 0.875rem;
}

/* Preview Note */
.preview-note {
  margin: 0.75rem 0 0;
  padding: 0.75rem;
  font-size: 0.75rem;
  color: var(--preview-gray-500);
  background: var(--preview-gray-50);
  border-radius: calc(var(--preview-radius) - 2px);
  text-align: center;
}
</style>
