<template>
    <div class="seo-form">
        <!-- Tab Navigation -->
        <div class="seo-tabs">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                :class="[
                    'seo-tab',
                    { 'seo-tab--active': activeTab === tab.id },
                ]"
                @click="activeTab = tab.id"
            >
                <component :is="tab.icon" class="seo-tab__icon" />
                <span class="seo-tab__label">{{ tab.label }}</span>
            </button>
        </div>

        <!-- Tab Content -->
        <div class="seo-tab-content">
            <!-- Basic SEO Tab -->
            <div v-show="activeTab === 'basic'" class="seo-tab-panel">
                <div class="seo-field">
                    <label for="seo-title" class="seo-label">
                        Meta Title
                        <span class="seo-char-count" :class="titleCountClass">
                            {{ form.title.length }}/{{ titleMaxLength }}
                        </span>
                    </label>
                    <input
                        id="seo-title"
                        v-model="form.title"
                        type="text"
                        class="seo-input"
                        placeholder="Enter page title..."
                        :maxlength="70"
                    />
                    <p class="seo-hint">
                        Recommended: 50-60 characters for optimal display in
                        search results.
                    </p>
                </div>

                <div class="seo-field">
                    <label for="seo-description" class="seo-label">
                        Meta Description
                        <span
                            class="seo-char-count"
                            :class="descriptionCountClass"
                        >
                            {{ form.description.length }}/{{
                                descriptionMaxLength
                            }}
                        </span>
                    </label>
                    <textarea
                        id="seo-description"
                        v-model="form.description"
                        rows="3"
                        class="seo-textarea"
                        placeholder="Enter meta description..."
                        :maxlength="200"
                    />
                    <p class="seo-hint">
                        Recommended: 150-160 characters. Make it compelling to
                        improve click-through rates.
                    </p>
                </div>

                <div class="seo-field">
                    <label class="seo-label">Focus Keywords</label>
                    <KeywordManager
                        v-model="form.focusKeywords"
                        :max-keywords="5"
                        :max-synonyms="5"
                    />
                </div>
            </div>

            <!-- Social Media Tab -->
            <div v-show="activeTab === 'social'" class="seo-tab-panel">
                <!-- Open Graph Section -->
                <fieldset class="seo-fieldset">
                    <legend class="seo-legend">
                        <svg
                            class="seo-legend__icon"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"
                            />
                        </svg>
                        Open Graph (Facebook, LinkedIn)
                    </legend>

                    <div class="seo-field">
                        <label for="og-title" class="seo-label">OG Title</label>
                        <input
                            id="og-title"
                            v-model="form.ogTitle"
                            type="text"
                            class="seo-input"
                            placeholder="Defaults to meta title"
                            :maxlength="70"
                        />
                    </div>

                    <div class="seo-field">
                        <label for="og-description" class="seo-label"
                            >OG Description</label
                        >
                        <textarea
                            id="og-description"
                            v-model="form.ogDescription"
                            rows="2"
                            class="seo-textarea"
                            placeholder="Defaults to meta description"
                            :maxlength="200"
                        />
                    </div>

                    <div class="seo-field">
                        <label for="og-image" class="seo-label"
                            >OG Image URL</label
                        >
                        <input
                            id="og-image"
                            v-model="form.ogImage"
                            type="url"
                            class="seo-input"
                            placeholder="https://example.com/image.jpg"
                        />
                        <p class="seo-hint">
                            Recommended size: 1200x630 pixels.
                        </p>
                    </div>

                    <div class="seo-field">
                        <label for="og-type" class="seo-label">OG Type</label>
                        <select
                            id="og-type"
                            v-model="form.ogType"
                            class="seo-select"
                        >
                            <option value="website">Website</option>
                            <option value="article">Article</option>
                            <option value="product">Product</option>
                            <option value="profile">Profile</option>
                            <option value="book">Book</option>
                        </select>
                    </div>
                </fieldset>

                <!-- Twitter Card Section -->
                <fieldset class="seo-fieldset">
                    <legend class="seo-legend">
                        <svg
                            class="seo-legend__icon"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"
                            />
                        </svg>
                        Twitter Card
                    </legend>

                    <div class="seo-field">
                        <label for="twitter-card" class="seo-label"
                            >Card Type</label
                        >
                        <select
                            id="twitter-card"
                            v-model="form.twitterCard"
                            class="seo-select"
                        >
                            <option value="summary">Summary</option>
                            <option value="summary_large_image">
                                Summary with Large Image
                            </option>
                            <option value="app">App</option>
                            <option value="player">Player</option>
                        </select>
                    </div>

                    <div class="seo-field">
                        <label for="twitter-title" class="seo-label"
                            >Twitter Title</label
                        >
                        <input
                            id="twitter-title"
                            v-model="form.twitterTitle"
                            type="text"
                            class="seo-input"
                            placeholder="Defaults to OG title"
                            :maxlength="70"
                        />
                    </div>

                    <div class="seo-field">
                        <label for="twitter-description" class="seo-label"
                            >Twitter Description</label
                        >
                        <textarea
                            id="twitter-description"
                            v-model="form.twitterDescription"
                            rows="2"
                            class="seo-textarea"
                            placeholder="Defaults to OG description"
                            :maxlength="200"
                        />
                    </div>

                    <div class="seo-field">
                        <label for="twitter-image" class="seo-label"
                            >Twitter Image URL</label
                        >
                        <input
                            id="twitter-image"
                            v-model="form.twitterImage"
                            type="url"
                            class="seo-input"
                            placeholder="Defaults to OG image"
                        />
                        <p class="seo-hint">
                            Recommended size: 1200x600 pixels (2:1 ratio).
                        </p>
                    </div>
                </fieldset>
            </div>

            <!-- Advanced Tab -->
            <div v-show="activeTab === 'advanced'" class="seo-tab-panel">
                <div class="seo-field">
                    <label for="canonical" class="seo-label"
                        >Canonical URL</label
                    >
                    <input
                        id="canonical"
                        v-model="form.canonical"
                        type="url"
                        class="seo-input"
                        placeholder="https://example.com/page"
                    />
                    <p class="seo-hint">
                        Use this to prevent duplicate content issues.
                    </p>
                </div>

                <div class="seo-field">
                    <label for="robots" class="seo-label"
                        >Robots Directive</label
                    >
                    <select
                        id="robots"
                        v-model="form.robots"
                        class="seo-select"
                    >
                        <option value="index, follow">
                            Index, Follow (Default)
                        </option>
                        <option value="index, nofollow">
                            Index, No Follow
                        </option>
                        <option value="noindex, follow">
                            No Index, Follow
                        </option>
                        <option value="noindex, nofollow">
                            No Index, No Follow
                        </option>
                    </select>
                    <p class="seo-hint">
                        Control how search engines index this page.
                    </p>
                </div>

                <div class="seo-field">
                    <label for="schema-type" class="seo-label"
                        >Schema Type</label
                    >
                    <select
                        id="schema-type"
                        v-model="form.schemaType"
                        class="seo-select"
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
                        <option value="SoftwareApplication">
                            Software Application
                        </option>
                        <option value="WebPage">Web Page</option>
                    </select>
                </div>

                <div class="seo-field">
                    <label for="schema-jsonld" class="seo-label">
                        Custom JSON-LD Schema
                        <span v-if="jsonLdError" class="seo-error-badge"
                            >Invalid JSON</span
                        >
                    </label>
                    <textarea
                        id="schema-jsonld"
                        v-model="form.schemaJsonld"
                        rows="8"
                        class="seo-textarea seo-textarea--code"
                        :class="{ 'seo-textarea--error': jsonLdError }"
                        placeholder='{"@context": "https://schema.org", "@type": "Article", ...}'
                    />
                    <p class="seo-hint">
                        Override the auto-generated schema with custom JSON-LD.
                        <a
                            href="https://schema.org/"
                            target="_blank"
                            rel="noopener"
                            class="seo-link"
                        >
                            Learn more about Schema.org
                        </a>
                    </p>
                </div>
            </div>

            <!-- Analysis Tab -->
            <div v-show="activeTab === 'analysis'" class="seo-tab-panel">
                <SEOAnalyzer
                    :score="analysisReport?.score ?? 0"
                    :results="analysisReport?.results ?? []"
                    :loading="analyzing"
                />
                <div
                    v-if="analysisReport?.analyzedAt"
                    class="seo-analysis-timestamp"
                >
                    Last analyzed: {{ formatDate(analysisReport.analyzedAt) }}
                </div>
                <button
                    type="button"
                    class="seo-button seo-button--secondary"
                    :disabled="analyzing"
                    @click="runAnalysis"
                >
                    <svg
                        v-if="analyzing"
                        class="seo-spinner"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="3"
                            fill="none"
                            opacity="0.25"
                        />
                        <path
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        />
                    </svg>
                    {{ analyzing ? "Analyzing..." : "Run Analysis" }}
                </button>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="seo-preview-section">
            <h3 class="seo-preview-title">Search Preview</h3>
            <SEOPreview
                :title="form.title || 'Page Title'"
                :description="
                    form.description || 'Page description will appear here...'
                "
                :url="form.canonical || previewUrl"
            />
        </div>

        <!-- Auto-save indicator -->
        <div v-if="autoSave" class="seo-autosave">
            <span
                v-if="saving"
                class="seo-autosave__status seo-autosave__status--saving"
            >
                Saving...
            </span>
            <span
                v-else-if="lastSaved"
                class="seo-autosave__status seo-autosave__status--saved"
            >
                ✓ Saved
            </span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from "vue";
import { useDebounceFn } from "@vueuse/core";
import type {
    SEOData,
    SEOFormState,
    Keyword,
    AnalysisReport,
} from "../types/seo";
import KeywordManager from "./KeywordManager.vue";
import SEOAnalyzer from "./SEOAnalyzer.vue";
import SEOPreview from "./SEOPreview.vue";

/**
 * Props
 */
interface Props {
    modelType: string;
    modelId: number | string;
    initialData?: Partial<SEOData>;
    saveEndpoint?: string;
    analyzeEndpoint?: string;
    autoSave?: boolean;
    autoSaveDelay?: number;
}

const props = withDefaults(defineProps<Props>(), {
    initialData: () => ({}),
    saveEndpoint: "/api/seo",
    analyzeEndpoint: "/api/seo/analyze",
    autoSave: true,
    autoSaveDelay: 1500,
});

/**
 * Emits
 */
const emit = defineEmits<{
    saved: [data: SEOData];
    error: [error: Error];
    analyzed: [report: AnalysisReport];
}>();

/**
 * Configuration
 */
const titleMaxLength = 60;
const descriptionMaxLength = 160;

/**
 * Tabs
 */
const tabs = [
    { id: "basic", label: "Basic SEO", icon: "IconDocument" },
    { id: "social", label: "Social Media", icon: "IconShare" },
    { id: "advanced", label: "Advanced", icon: "IconCog" },
    { id: "analysis", label: "Analysis", icon: "IconChart" },
] as const;

type TabId = (typeof tabs)[number]["id"];

const activeTab = ref<TabId>("basic");

/**
 * Form State
 */
const form = reactive<SEOFormState>({
    title: props.initialData?.title ?? "",
    description: props.initialData?.description ?? "",
    focusKeywords: (props.initialData?.focusKeywords ?? []) as Keyword[],
    canonical: props.initialData?.canonical ?? "",
    robots: (typeof props.initialData?.robots === "string"
        ? props.initialData.robots
        : "index, follow") as string,
    ogTitle:
        props.initialData?.ogTitle ?? props.initialData?.openGraph?.title ?? "",
    ogDescription:
        props.initialData?.ogDescription ??
        props.initialData?.openGraph?.description ??
        "",
    ogImage:
        props.initialData?.ogImage ?? props.initialData?.openGraph?.image ?? "",
    ogType:
        props.initialData?.ogType ??
        props.initialData?.openGraph?.type ??
        "website",
    twitterCard:
        props.initialData?.twitterCardType ??
        (typeof props.initialData?.twitterCard === "object"
            ? props.initialData.twitterCard.card
            : "summary_large_image") ??
        "summary_large_image",
    twitterTitle:
        props.initialData?.twitterTitle ??
        (typeof props.initialData?.twitterCard === "object"
            ? props.initialData.twitterCard.title
            : "") ??
        "",
    twitterDescription:
        props.initialData?.twitterDescription ??
        (typeof props.initialData?.twitterCard === "object"
            ? props.initialData.twitterCard.description
            : "") ??
        "",
    twitterImage:
        props.initialData?.twitterImage ??
        (typeof props.initialData?.twitterCard === "object"
            ? props.initialData.twitterCard.image
            : "") ??
        "",
    schemaType: props.initialData?.schemaType ?? "",
    schemaJsonld:
        typeof props.initialData?.schemaJsonld === "string"
            ? props.initialData.schemaJsonld
            : props.initialData?.schemaJsonld
            ? JSON.stringify(props.initialData.schemaJsonld, null, 2)
            : "",
});

/**
 * State
 */
const saving = ref(false);
const lastSaved = ref<Date | null>(null);
const analyzing = ref(false);
const analysisReport = ref<AnalysisReport | null>(
    props.initialData?.analysisReport ?? null
);

/**
 * Computed
 */
const titleCountClass = computed(() => ({
    "seo-char-count--warning":
        form.title.length > titleMaxLength - 10 &&
        form.title.length <= titleMaxLength,
    "seo-char-count--error": form.title.length > titleMaxLength,
}));

const descriptionCountClass = computed(() => ({
    "seo-char-count--warning":
        form.description.length > descriptionMaxLength - 20 &&
        form.description.length <= descriptionMaxLength,
    "seo-char-count--error": form.description.length > descriptionMaxLength,
}));

const jsonLdError = computed(() => {
    if (!form.schemaJsonld) return false;
    try {
        JSON.parse(form.schemaJsonld);
        return false;
    } catch {
        return true;
    }
});

const previewUrl = computed(() => {
    if (typeof window !== "undefined") {
        return window.location.href;
    }
    return "https://example.com/page";
});

/**
 * Methods
 */
async function saveData(): Promise<void> {
    if (saving.value) return;

    saving.value = true;

    try {
        const response = await fetch(props.saveEndpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCSRFToken(),
            },
            body: JSON.stringify({
                model_type: props.modelType,
                model_id: props.modelId,
                ...formToPayload(),
            }),
        });

        if (!response.ok) {
            throw new Error(`Failed to save SEO data: ${response.statusText}`);
        }

        const data: SEOData = await response.json();
        lastSaved.value = new Date();
        emit("saved", data);
    } catch (error) {
        emit("error", error as Error);
    } finally {
        saving.value = false;
    }
}

async function runAnalysis(): Promise<void> {
    if (analyzing.value) return;

    analyzing.value = true;

    try {
        const response = await fetch(props.analyzeEndpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCSRFToken(),
            },
            body: JSON.stringify({
                model_type: props.modelType,
                model_id: props.modelId,
            }),
        });

        if (!response.ok) {
            throw new Error(`Failed to analyze: ${response.statusText}`);
        }

        const report: AnalysisReport = await response.json();
        analysisReport.value = report;
        emit("analyzed", report);
    } catch (error) {
        emit("error", error as Error);
    } finally {
        analyzing.value = false;
    }
}

function formToPayload(): Record<string, unknown> {
    return {
        title: form.title,
        description: form.description,
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
}

function getCSRFToken(): string {
    if (typeof document === "undefined") return "";
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute("content") ?? "";
}

function formatDate(dateString: string): string {
    try {
        return new Intl.DateTimeFormat("en-US", {
            dateStyle: "medium",
            timeStyle: "short",
        }).format(new Date(dateString));
    } catch {
        return dateString;
    }
}

/**
 * Auto-save with debounce
 */
const debouncedSave = useDebounceFn(() => {
    if (props.autoSave) {
        saveData();
    }
}, props.autoSaveDelay);

// Watch form changes for auto-save
watch(
    () => ({ ...form }),
    () => {
        debouncedSave();
    },
    { deep: true }
);

/**
 * Lifecycle
 */
onMounted(() => {
    // Optionally run initial analysis
});

// Expose methods for parent components
defineExpose({
    save: saveData,
    analyze: runAnalysis,
});
</script>

<style scoped>
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
    --seo-gray-900: #111827;
    --seo-radius: 0.5rem;
    --seo-transition: 150ms ease;

    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
        Roboto, sans-serif;
    color: var(--seo-gray-800);
}

/* Tabs */
.seo-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 1px solid var(--seo-gray-200);
    margin-bottom: 1.5rem;
}

.seo-tab {
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

.seo-tab:hover {
    color: var(--seo-gray-700);
}

.seo-tab--active {
    color: var(--seo-primary);
    border-bottom-color: var(--seo-primary);
}

.seo-tab__icon {
    width: 1.25rem;
    height: 1.25rem;
}

/* Tab Content */
.seo-tab-content {
    min-height: 400px;
}

.seo-tab-panel {
    animation: fadeIn 200ms ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Fields */
.seo-field {
    margin-bottom: 1.25rem;
}

.seo-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--seo-gray-700);
    margin-bottom: 0.5rem;
}

.seo-input,
.seo-textarea,
.seo-select {
    width: 100%;
    padding: 0.625rem 0.875rem;
    font-size: 0.9375rem;
    color: var(--seo-gray-800);
    background: white;
    border: 1px solid var(--seo-gray-300);
    border-radius: var(--seo-radius);
    transition: border-color var(--seo-transition),
        box-shadow var(--seo-transition);
}

.seo-input:focus,
.seo-textarea:focus,
.seo-select:focus {
    outline: none;
    border-color: var(--seo-primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.seo-textarea {
    resize: vertical;
    min-height: 80px;
}

.seo-textarea--code {
    font-family: "SF Mono", "Fira Code", monospace;
    font-size: 0.8125rem;
}

.seo-textarea--error {
    border-color: var(--seo-error);
}

.seo-hint {
    margin-top: 0.375rem;
    font-size: 0.8125rem;
    color: var(--seo-gray-500);
}

.seo-link {
    color: var(--seo-primary);
    text-decoration: none;
}

.seo-link:hover {
    text-decoration: underline;
}

/* Character Counters */
.seo-char-count {
    font-size: 0.75rem;
    font-weight: 400;
    color: var(--seo-gray-400);
}

.seo-char-count--warning {
    color: var(--seo-warning);
}

.seo-char-count--error {
    color: var(--seo-error);
}

/* Error Badge */
.seo-error-badge {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    color: white;
    background: var(--seo-error);
    border-radius: 9999px;
    margin-left: 0.5rem;
}

/* Fieldsets */
.seo-fieldset {
    margin: 0 0 1.5rem;
    padding: 1.25rem;
    border: 1px solid var(--seo-gray-200);
    border-radius: var(--seo-radius);
    background: var(--seo-gray-50);
}

.seo-legend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0 0.5rem;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--seo-gray-700);
}

.seo-legend__icon {
    width: 1.125rem;
    height: 1.125rem;
}

/* Buttons */
.seo-button {
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

.seo-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.seo-button--secondary {
    color: var(--seo-gray-700);
    background: white;
    border: 1px solid var(--seo-gray-300);
}

.seo-button--secondary:hover:not(:disabled) {
    background: var(--seo-gray-50);
    border-color: var(--seo-gray-400);
}

/* Spinner */
.seo-spinner {
    width: 1rem;
    height: 1rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Preview Section */
.seo-preview-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--seo-gray-200);
}

.seo-preview-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--seo-gray-700);
    margin: 0 0 1rem;
}

/* Analysis */
.seo-analysis-timestamp {
    margin: 1rem 0;
    font-size: 0.8125rem;
    color: var(--seo-gray-500);
}

/* Auto-save */
.seo-autosave {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 50;
}

.seo-autosave__status {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 1rem;
    font-size: 0.8125rem;
    font-weight: 500;
    border-radius: 9999px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.seo-autosave__status--saving {
    color: var(--seo-gray-600);
    background: white;
}

.seo-autosave__status--saved {
    color: var(--seo-success);
    background: white;
}
</style>
