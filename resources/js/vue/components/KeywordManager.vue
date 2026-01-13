<template>
  <div class="keyword-manager">
    <!-- Keyword List -->
    <TransitionGroup name="keyword-list" tag="div" class="keyword-list">
      <div
        v-for="(keyword, index) in modelValue"
        :key="keyword.id"
        class="keyword-item"
        :class="{ 'keyword-item--primary': keyword.isPrimary }"
      >
        <div class="keyword-header">
          <div class="keyword-main">
            <button
              type="button"
              class="keyword-primary-toggle"
              :class="{ 'keyword-primary-toggle--active': keyword.isPrimary }"
              :title="keyword.isPrimary ? 'Primary keyword' : 'Set as primary'"
              @click="togglePrimary(index)"
            >
              <svg viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
              </svg>
            </button>
            <input
              v-model="modelValue[index].keyword"
              type="text"
              class="keyword-input"
              placeholder="Enter keyword..."
              @input="emitUpdate"
            />
          </div>
          <button
            type="button"
            class="keyword-remove"
            title="Remove keyword"
            @click="removeKeyword(index)"
          >
            <svg viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>

        <!-- Synonyms Section -->
        <div class="keyword-synonyms">
          <label class="keyword-synonyms-label">
            Synonyms
            <span class="keyword-synonyms-count">
              {{ keyword.synonyms.length }}/{{ maxSynonyms }}
            </span>
          </label>
          <div class="synonym-chips">
            <TransitionGroup name="chip-list" tag="div" class="chip-container">
              <span
                v-for="(synonym, synIndex) in keyword.synonyms"
                :key="`${keyword.id}-syn-${synIndex}`"
                class="synonym-chip"
              >
                {{ synonym }}
                <button
                  type="button"
                  class="synonym-chip-remove"
                  @click="removeSynonym(index, synIndex)"
                >
                  <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                  </svg>
                </button>
              </span>
            </TransitionGroup>
            <input
              v-if="keyword.synonyms.length < maxSynonyms"
              v-model="synonymInputs[index]"
              type="text"
              class="synonym-input"
              placeholder="Add synonym..."
              @keydown.enter.prevent="addSynonym(index)"
              @keydown.comma.prevent="addSynonym(index)"
              @blur="addSynonym(index)"
            />
          </div>
        </div>
      </div>
    </TransitionGroup>

    <!-- Add Keyword Button -->
    <button
      v-if="modelValue.length < maxKeywords"
      type="button"
      class="keyword-add"
      @click="addKeyword"
    >
      <svg viewBox="0 0 20 20" fill="currentColor">
        <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
      </svg>
      Add Keyword
      <span class="keyword-add-count">{{ modelValue.length }}/{{ maxKeywords }}</span>
    </button>

    <!-- Empty State -->
    <div v-if="modelValue.length === 0" class="keyword-empty">
      <p>No focus keywords added yet.</p>
      <p class="keyword-empty-hint">
        Focus keywords help analyze how well your content is optimized for specific terms.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import type { Keyword, KeywordManagerProps } from '../types/seo';

const props = withDefaults(defineProps<KeywordManagerProps>(), {
  modelValue: () => [],
  maxKeywords: 5,
  maxSynonyms: 5,
});

const emit = defineEmits<{
  'update:modelValue': [keywords: Keyword[]];
}>();

// Track synonym input values for each keyword
const synonymInputs = reactive<Record<number, string>>({});

// Generate unique ID
function generateId(): string {
  return `kw-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
}

// Emit update helper
function emitUpdate(): void {
  emit('update:modelValue', [...props.modelValue]);
}

// Add new keyword
function addKeyword(): void {
  if (props.modelValue.length >= props.maxKeywords) return;

  const newKeyword: Keyword = {
    id: generateId(),
    keyword: '',
    isPrimary: props.modelValue.length === 0, // First keyword is primary by default
    synonyms: [],
  };

  emit('update:modelValue', [...props.modelValue, newKeyword]);
}

// Remove keyword
function removeKeyword(index: number): void {
  const updated = [...props.modelValue];
  const wasRemoved = updated.splice(index, 1)[0];

  // If removed keyword was primary, make first remaining keyword primary
  if (wasRemoved.isPrimary && updated.length > 0) {
    updated[0].isPrimary = true;
  }

  // Clean up synonym input
  delete synonymInputs[index];

  emit('update:modelValue', updated);
}

// Toggle primary keyword
function togglePrimary(index: number): void {
  const updated = props.modelValue.map((kw, i) => ({
    ...kw,
    isPrimary: i === index,
  }));

  emit('update:modelValue', updated);
}

// Add synonym to keyword
function addSynonym(keywordIndex: number): void {
  const value = synonymInputs[keywordIndex]?.trim();
  if (!value) return;

  const keyword = props.modelValue[keywordIndex];
  if (!keyword || keyword.synonyms.length >= props.maxSynonyms) return;

  // Check for duplicates
  if (keyword.synonyms.includes(value)) {
    synonymInputs[keywordIndex] = '';
    return;
  }

  const updated = [...props.modelValue];
  updated[keywordIndex] = {
    ...updated[keywordIndex],
    synonyms: [...updated[keywordIndex].synonyms, value],
  };

  synonymInputs[keywordIndex] = '';
  emit('update:modelValue', updated);
}

// Remove synonym from keyword
function removeSynonym(keywordIndex: number, synonymIndex: number): void {
  const updated = [...props.modelValue];
  updated[keywordIndex] = {
    ...updated[keywordIndex],
    synonyms: updated[keywordIndex].synonyms.filter((_, i) => i !== synonymIndex),
  };

  emit('update:modelValue', updated);
}

// Initialize synonym inputs when modelValue changes
watch(
  () => props.modelValue,
  (keywords) => {
    keywords.forEach((_, index) => {
      if (synonymInputs[index] === undefined) {
        synonymInputs[index] = '';
      }
    });
  },
  { immediate: true }
);
</script>

<style scoped>
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

/* Keyword List */
.keyword-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

/* Keyword Item */
.keyword-item {
  padding: 1rem;
  background: var(--km-gray-50);
  border: 1px solid var(--km-gray-200);
  border-radius: var(--km-radius);
  transition: border-color var(--km-transition), box-shadow var(--km-transition);
}

.keyword-item:hover {
  border-color: var(--km-gray-300);
}

.keyword-item--primary {
  border-color: var(--km-primary);
  background: var(--km-primary-light);
}

.keyword-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.keyword-main {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.keyword-primary-toggle {
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

.keyword-primary-toggle:hover {
  color: var(--km-star);
  transform: scale(1.1);
}

.keyword-primary-toggle--active {
  color: var(--km-star);
}

.keyword-primary-toggle svg {
  width: 1.25rem;
  height: 1.25rem;
}

.keyword-input {
  flex: 1;
  padding: 0.5rem 0.75rem;
  font-size: 0.9375rem;
  color: var(--km-gray-800);
  background: white;
  border: 1px solid var(--km-gray-200);
  border-radius: var(--km-radius);
  transition: border-color var(--km-transition), box-shadow var(--km-transition);
}

.keyword-input:focus {
  outline: none;
  border-color: var(--km-primary);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.keyword-remove {
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

.keyword-remove:hover {
  color: var(--km-danger);
  background: rgba(239, 68, 68, 0.1);
}

.keyword-remove svg {
  width: 1.125rem;
  height: 1.125rem;
}

/* Synonyms Section */
.keyword-synonyms {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--km-gray-200);
}

.keyword-synonyms-label {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--km-gray-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.5rem;
}

.keyword-synonyms-count {
  font-weight: 400;
  color: var(--km-gray-400);
}

.synonym-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  align-items: center;
}

.chip-container {
  display: contents;
}

.synonym-chip {
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
  from {
    opacity: 0;
    transform: scale(0.8);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.synonym-chip-remove {
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

.synonym-chip-remove:hover {
  color: var(--km-danger);
  background: rgba(239, 68, 68, 0.1);
}

.synonym-chip-remove svg {
  width: 0.75rem;
  height: 0.75rem;
}

.synonym-input {
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

.synonym-input:focus {
  outline: none;
  border-color: var(--km-primary);
  border-style: solid;
  background: white;
}

.synonym-input::placeholder {
  color: var(--km-gray-400);
}

/* Add Button */
.keyword-add {
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

.keyword-add:hover {
  color: var(--km-primary);
  border-color: var(--km-primary);
  background: var(--km-primary-light);
}

.keyword-add svg {
  width: 1.125rem;
  height: 1.125rem;
}

.keyword-add-count {
  font-size: 0.75rem;
  color: var(--km-gray-400);
  margin-left: auto;
}

/* Empty State */
.keyword-empty {
  padding: 1.5rem;
  text-align: center;
  color: var(--km-gray-500);
  font-size: 0.875rem;
}

.keyword-empty p {
  margin: 0;
}

.keyword-empty-hint {
  font-size: 0.8125rem;
  color: var(--km-gray-400);
  margin-top: 0.5rem !important;
}

/* Transitions */
.keyword-list-enter-active,
.keyword-list-leave-active {
  transition: all 200ms ease;
}

.keyword-list-enter-from,
.keyword-list-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

.keyword-list-move {
  transition: transform 200ms ease;
}

.chip-list-enter-active,
.chip-list-leave-active {
  transition: all 150ms ease;
}

.chip-list-enter-from,
.chip-list-leave-to {
  opacity: 0;
  transform: scale(0.8);
}
</style>
