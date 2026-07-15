<script setup lang="ts">
import { ref } from 'vue'
import { withBase } from 'vitepress'
import CommitField from './CommitField.vue'

// Every number and version on this page is a verified fact carried over from
// the packages themselves — nothing here is a marketing claim invented for the
// landing page. If a figure changes, it changes here and in the README together.
const INSTALL = 'composer require rankbeam/laravel-seo'

const copied = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

async function copyInstall() {
  try {
    await navigator.clipboard.writeText(INSTALL)
    copied.value = true
    clearTimeout(timer)
    timer = setTimeout(() => (copied.value = false), 2000)
  } catch {
    // Clipboard can be blocked (insecure origin, denied permission). The command
    // is selectable text either way, so there is nothing to recover from.
  }
}

const packages = [
  { name: 'rankbeam/laravel-seo', version: 'v3.11.1', note: 'Free · MIT' },
  { name: 'rankbeam/laravel-seo-pro', version: 'v2.30.0', note: 'Commercial' },
  { name: 'rankbeam/laravel-seo-filament', version: 'v1.5.0', note: 'Free · Filament 4–5' },
  { name: 'Requires', version: 'Laravel 11–13', note: 'PHP 8.2–8.4' },
]

const core = [
  {
    title: 'Typed, layered resolution',
    body: 'Six precedence layers resolve into one typed SEOData object. Null never overwrites a lower layer, so every page renders something sensible.',
    link: '/concepts/resolver-precedence',
    linkText: 'How resolution works',
  },
  {
    title: 'One directive renders the head',
    body: '@seo($post) emits title, description, canonical, robots, Open Graph, Twitter Cards and JSON-LD — XSS-hardened.',
    link: '/guide/blade',
    linkText: 'Blade guide',
  },
  {
    title: 'Linked JSON-LD schema graph',
    body: 'Organization, WebSite and WebPage nodes cross-linked via stable @id values, plus builders for Article, Product, Breadcrumb, FAQ and LocalBusiness.',
    link: '/guide/schema',
    linkText: 'Schema graph',
  },
  {
    title: 'Sitemap registry',
    body: 'Register named sitemap sources from models, closures or URL lists. The package writes the files and serves them for you.',
    link: '/guide/sitemaps',
    linkText: 'Sitemap registry',
  },
]

const pro = [
  {
    title: 'Technical-SEO audit',
    body: 'A queued scan pipeline with severity-ranked issues and a transparent 0–100 score.',
    link: '/pro/scan-issues',
  },
  {
    title: 'Broken links & 404 recovery',
    body: 'A typed link crawler, a hardened redirect manager and a no-IP 404 monitor with one-click recovery.',
    link: '/pro/broken-links',
  },
  {
    title: 'Search Console insights',
    body: 'Pull real query and page data back into the audit, so a score is checked against what Google actually reports.',
    link: '/pro/search-console-insights',
  },
  {
    title: 'AI assist — your key',
    body: 'Title and meta-description suggestions on your own Anthropic, OpenAI, Google or local account. Off by default.',
    link: '/pro/ai-assist',
  },
]

// Counted from a real run of each suite on 2026-07-14, not carried over from a
// README: core 632 passed / 4 skipped, Pro 1,392 passed / 3 skipped (v2.30.0),
// Filament 117 passed. Re-run the suites before editing these.
const proof = [
  { figure: '632', label: 'tests, free core' },
  { figure: '1,392', label: 'tests, Pro engine' },
  { figure: '117', label: 'tests, Filament UI' },
  { figure: 'Zero', label: 'telemetry, ever' },
]
</script>

<template>
  <div class="rb-home">
    <!-- Hero ------------------------------------------------------------ -->
    <section class="rb-hero">
      <CommitField />
      <div class="rb-container rb-hero-inner">
        <p class="rb-eyebrow">Laravel 11–13 · PHP 8.2–8.4</p>
        <h1 class="rb-h1">The headless SEO engine for Laravel</h1>
        <p class="rb-lede">
          Typed, Git-native metadata resolution, a linked JSON-LD schema graph and XML sitemaps
          in a free MIT core — rendered the same in Blade, Inertia or a JSON API.
        </p>

        <div class="rb-install">
          <code><span class="rb-prompt">$</span> {{ INSTALL }}</code>
          <button type="button" class="rb-copy" @click="copyInstall">
            {{ copied ? 'Copied' : 'Copy' }}
          </button>
        </div>

        <div class="rb-actions">
          <a class="rb-btn rb-btn-primary" :href="withBase('/guide/quickstart')">Quickstart</a>
          <a class="rb-btn rb-btn-secondary" :href="withBase('/guide/why-rankbeam')">What is Rankbeam?</a>
        </div>

        <ul class="rb-notes">
          <li><span class="rb-check" aria-hidden="true">✓</span> Free MIT core</li>
          <li><span class="rb-check" aria-hidden="true">✓</span> No runtime license check</li>
          <li><span class="rb-check" aria-hidden="true">✓</span> No telemetry</li>
        </ul>
      </div>
    </section>

    <!-- Version rail ---------------------------------------------------- -->
    <section class="rb-rail">
      <div class="rb-container rb-rail-grid">
        <div v-for="pkg in packages" :key="pkg.name" class="rb-rail-item">
          <strong>{{ pkg.version }}</strong>
          <span>{{ pkg.name }}</span>
          <small>{{ pkg.note }}</small>
        </div>
      </div>
    </section>

    <!-- Core ------------------------------------------------------------ -->
    <section class="rb-section">
      <div class="rb-container">
        <div class="rb-head">
          <p class="rb-eyebrow rb-eyebrow-dark">The free core</p>
          <h2 class="rb-h2">SEO as part of the application, not a separate admin product</h2>
          <p class="rb-sub">
            Defaults live in <code>config/seo.php</code> and your code — version-controlled,
            code-reviewed, reproducible across environments. No click-through wizard to redo per install.
          </p>
        </div>

        <div class="rb-split">
          <ul class="rb-features">
            <li v-for="item in core" :key="item.title">
              <h3>{{ item.title }}</h3>
              <p>{{ item.body }}</p>
              <a :href="withBase(item.link)">{{ item.linkText }} →</a>
            </li>
          </ul>

          <figure class="rb-code">
            <figcaption>resources/views/posts/show.blade.php</figcaption>
            <pre><code><span class="c-dim">&lt;!DOCTYPE html&gt;</span>
<span class="c-key">&lt;html&gt;</span>
<span class="c-key">&lt;head&gt;</span>
    <span class="c-method">@seo</span>(<span class="c-var">$post</span>)
<span class="c-key">&lt;/head&gt;</span></code></pre>
            <figcaption class="rb-code-note">
              Title, description, canonical, robots, Open Graph, Twitter Cards and JSON-LD.
            </figcaption>
          </figure>
        </div>
      </div>
    </section>

    <!-- Pro ------------------------------------------------------------- -->
    <section class="rb-section rb-section-dark">
      <div class="rb-container">
        <div class="rb-head">
          <p class="rb-eyebrow">Pro</p>
          <h2 class="rb-h2">The audit layer, running headless</h2>
          <p class="rb-sub rb-sub-dark">
            Every Pro feature runs from artisan and your scheduler. The Filament dashboard is optional,
            and licensing is install-time only — nothing phones home.
          </p>
        </div>

        <div class="rb-grid">
          <a v-for="item in pro" :key="item.title" class="rb-card" :href="withBase(item.link)">
            <h3>{{ item.title }}</h3>
            <p>{{ item.body }}</p>
          </a>
        </div>
      </div>
    </section>

    <!-- Proof ----------------------------------------------------------- -->
    <section class="rb-section rb-section-soft">
      <div class="rb-container">
        <div class="rb-head">
          <p class="rb-eyebrow rb-eyebrow-dark">Proof</p>
          <h2 class="rb-h2">Tested, and quiet by default</h2>
        </div>
        <div class="rb-proof">
          <div v-for="fact in proof" :key="fact.label" class="rb-fact">
            <strong>{{ fact.figure }}</strong>
            <span>{{ fact.label }}</span>
          </div>
        </div>
        <p class="rb-proof-note">
          Blade is browser-proven; Inertia (Vue/React/Svelte) and Livewire are tested against a shared
          <a :href="withBase('/contributing/rendering-contract')">rendering contract</a>.
          Counts are passing tests: the core also skips 4, Pro skips 3.
        </p>
      </div>
    </section>

    <!-- Next ------------------------------------------------------------ -->
    <section class="rb-section rb-next">
      <div class="rb-container">
        <h2 class="rb-h2">Install to a fully-rendered &lt;head&gt; in five minutes</h2>
        <div class="rb-actions">
          <a class="rb-btn rb-btn-primary" :href="withBase('/guide/quickstart')">Quickstart</a>
          <a class="rb-btn rb-btn-secondary" :href="withBase('/guide/demo')">Run the demo</a>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Landing-only styles. The docs' global token map lives in custom.css; this
   file just spends those tokens. Nothing here leaks onto a docs page. */

.rb-home {
  --rb-space-1: 8px;
  --rb-space-2: 16px;
  --rb-space-3: 24px;
  --rb-space-4: 32px;
  --rb-space-6: 48px;
  --rb-space-8: 64px;
  --rb-space-12: 96px;
  overflow-x: clip;
}

.rb-container {
  width: min(calc(100% - var(--rb-space-4)), 1184px);
  margin-inline: auto;
}

/* ---- Type ---- */
.rb-h1 {
  max-width: 16ch;
  margin: 0 0 var(--rb-space-3);
  font-size: clamp(2.5rem, 5.6vw, 4.75rem);
  font-weight: 560;
  letter-spacing: -.045em;
  line-height: 1.04;
  text-wrap: balance;
}
.rb-h2 {
  max-width: 22ch;
  margin: 0 0 var(--rb-space-2);
  font-size: clamp(1.9rem, 3.4vw, 2.9rem);
  font-weight: 640;
  letter-spacing: -.035em;
  line-height: 1.1;
  text-wrap: balance;
}
.rb-eyebrow {
  display: flex;
  align-items: center;
  gap: var(--rb-space-1);
  margin: 0 0 var(--rb-space-2);
  color: var(--rb-night-muted);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
}
.rb-eyebrow::before {
  width: 24px;
  height: 1px;
  content: "";
  background: currentColor;
}
.rb-eyebrow-dark { color: var(--vp-c-text-2); }
.rb-lede {
  max-width: 60ch;
  margin: 0;
  color: #d9dbea;
  font-size: clamp(1.05rem, 1.6vw, 1.25rem);
  line-height: 1.6;
}
.rb-sub {
  max-width: 62ch;
  margin: 0;
  color: var(--vp-c-text-2);
  font-size: 1.0625rem;
  line-height: 1.65;
}
.rb-sub code {
  padding: 2px 6px;
  background: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-divider);
  border-radius: 6px;
  font-size: .9em;
}
.rb-sub-dark { color: var(--rb-console-muted); }

/* ---- Hero ---- */
.rb-hero {
  position: relative;
  overflow: hidden;
  padding-block: var(--rb-space-12) var(--rb-space-8);
  color: var(--rb-night-ink);
  background:
    radial-gradient(circle at 50% 82%, color-mix(in srgb, var(--rb-action) 72%, transparent), transparent 30rem),
    radial-gradient(circle at 6% 70%, color-mix(in srgb, var(--rb-brand) 14%, transparent), transparent 22rem),
    var(--rb-night);
}
.rb-hero::after {
  position: absolute;
  inset: 0;
  z-index: 1;
  content: "";
  background: radial-gradient(ellipse 64% 48% at 26% 26%, color-mix(in srgb, var(--rb-night) 96%, transparent) 0, color-mix(in srgb, var(--rb-night) 76%, transparent) 54%, transparent 100%);
  pointer-events: none;
}
.rb-hero-inner { position: relative; z-index: 2; }

.rb-install {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--rb-space-2);
  width: min(100%, 460px);
  margin-top: var(--rb-space-4);
  padding: 6px 6px 6px var(--rb-space-2);
  color: var(--rb-console-ink);
  background: color-mix(in srgb, var(--rb-console) 82%, transparent);
  border: 1px solid var(--rb-console-line);
  border-radius: var(--rb-radius-s);
  font-size: 14px;
}
.rb-install code {
  overflow-x: auto;
  font-family: var(--vp-font-family-mono);
  white-space: nowrap;
}
.rb-prompt { color: #b7d79e; }
.rb-copy {
  flex: none;
  min-height: 32px;
  padding-inline: 12px;
  color: var(--rb-console-ink);
  background: transparent;
  border: 1px solid var(--rb-console-line);
  border-radius: var(--rb-radius-pill);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.rb-copy:hover { background: var(--rb-console-line); }

.rb-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--rb-space-2);
  margin-top: var(--rb-space-4);
}
.rb-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding-inline: var(--rb-space-3);
  border: 1px solid transparent;
  border-radius: var(--rb-radius-pill);
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  transition: background-color 180ms var(--rb-ease), border-color 180ms var(--rb-ease), color 180ms var(--rb-ease);
}
/* The one primary-CTA treatment. Identical here, in the closing CTA, and in
   the docs' own buttons. Brand red is never a button. */
.rb-btn-primary {
  color: var(--rb-action-ink);
  background: var(--rb-action);
  box-shadow: 0 8px 24px color-mix(in srgb, var(--rb-action) 30%, transparent);
}
/* In the hero the pill sits on the action glow — the same blue as itself — so
   its edge barely separates from the background. This is a wide, low-density
   haze of night rather than a pool: no tight spread, so the tile field still
   reads straight through it and the button simply sits a little forward of the
   page. Deliberately below the 3:1 that WCAG 1.4.11 asks of a control's
   boundary (see note in the PR): the density needed to clear that floor is the
   density that hides the pattern, and the pattern won. */
.rb-hero .rb-btn-primary {
  box-shadow:
    0 4px 20px 2px color-mix(in srgb, var(--rb-night) 42%, transparent),
    0 14px 56px 14px color-mix(in srgb, var(--rb-night) 26%, transparent),
    0 8px 24px color-mix(in srgb, var(--rb-action) 30%, transparent);
}
.rb-btn-primary:hover { background: var(--rb-action-hover); color: var(--rb-action-ink); }
.rb-btn-secondary {
  color: var(--rb-night-ink);
  border-color: color-mix(in srgb, var(--rb-night-ink) 42%, transparent);
}
.rb-btn-secondary:hover {
  background: color-mix(in srgb, var(--rb-night-ink) 10%, transparent);
  color: var(--rb-night-ink);
}
.rb-next .rb-btn-secondary { color: var(--vp-c-text-1); border-color: var(--rb-line-strong); }
.rb-next .rb-btn-secondary:hover { background: var(--vp-c-bg-soft); color: var(--vp-c-text-1); }

.rb-notes {
  display: flex;
  flex-wrap: wrap;
  gap: var(--rb-space-2) var(--rb-space-3);
  padding: 0;
  margin: var(--rb-space-4) 0 0;
  color: #d9dbea;
  font-size: 14px;
  list-style: none;
}
.rb-notes li { display: inline-flex; align-items: center; gap: var(--rb-space-1); }
.rb-check {
  display: inline-grid;
  width: 20px;
  height: 20px;
  place-items: center;
  color: #79c99e;
  background: color-mix(in srgb, #79c99e 18%, transparent);
  border-radius: 50%;
  font-size: 11px;
  font-weight: 800;
}

/* ---- Version rail ---- */
.rb-rail {
  padding-block: var(--rb-space-3);
  background: var(--vp-c-bg-alt);
  border-bottom: 1px solid var(--vp-c-divider);
}
.rb-rail-grid { display: grid; grid-template-columns: repeat(4, 1fr); }
.rb-rail-item { padding-inline: var(--rb-space-3); border-left: 1px solid var(--vp-c-divider); }
.rb-rail-item:first-child { padding-left: 0; border-left: 0; }
.rb-rail-item strong, .rb-rail-item span, .rb-rail-item small { display: block; }
.rb-rail-item strong { font-size: 1.0625rem; font-weight: 700; letter-spacing: -.01em; }
.rb-rail-item span {
  overflow: hidden;
  color: var(--vp-c-text-2);
  font-family: var(--vp-font-family-mono);
  font-size: 12px;
  text-overflow: ellipsis;
}
.rb-rail-item small { color: var(--vp-c-text-3); font-size: 12px; }

/* ---- Sections ---- */
.rb-section { padding-block: var(--rb-space-12); background: var(--vp-c-bg); }
.rb-section-soft { background: var(--vp-c-bg-alt); border-block: 1px solid var(--vp-c-divider); }
.rb-section-dark {
  color: var(--rb-console-ink);
  background:
    radial-gradient(circle at 84% 16%, color-mix(in srgb, var(--rb-action) 14%, transparent), transparent 28rem),
    var(--rb-night);
}
.rb-head { margin-bottom: var(--rb-space-6); }
.rb-section-dark .rb-h2 { color: var(--rb-night-ink); }

.rb-split {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--rb-space-8);
  align-items: start;
}

.rb-features { display: grid; gap: var(--rb-space-4); padding: 0; margin: 0; list-style: none; }
.rb-features li { min-width: 0; }
.rb-features h3 {
  margin: 0 0 6px;
  font-size: 1.0625rem;
  font-weight: 680;
  letter-spacing: -.01em;
}
.rb-features p { margin: 0 0 6px; color: var(--vp-c-text-2); line-height: 1.6; }
.rb-features a { color: var(--vp-c-brand-1); font-size: 14px; font-weight: 650; text-decoration: none; }
.rb-features a:hover { text-decoration: underline; }

/* ---- Code card ---- */
.rb-code {
  min-width: 0;
  margin: 0;
  overflow: hidden;
  color: var(--rb-console-ink);
  background: var(--rb-console);
  border: 1px solid var(--rb-console-line);
  border-radius: var(--rb-radius-l);
}
.rb-code figcaption {
  padding: 12px var(--rb-space-3);
  color: var(--rb-console-muted);
  background: var(--rb-console-surface);
  border-bottom: 1px solid var(--rb-console-line);
  font-family: var(--vp-font-family-mono);
  font-size: 12px;
}
.rb-code pre {
  overflow-x: auto;
  padding: var(--rb-space-3);
  margin: 0;
  font-family: var(--vp-font-family-mono);
  font-size: 13.5px;
  line-height: 1.8;
}
.rb-code-note {
  border-top: 1px solid var(--rb-console-line);
  border-bottom: 0 !important;
  font-family: var(--vp-font-family-base) !important;
  line-height: 1.5;
}
.c-dim { color: var(--rb-console-muted); }
.c-key { color: var(--rb-code-blue); }
.c-method { color: #e8c17b; }
.c-var { color: #b7d79e; }

/* ---- Pro grid ---- */
.rb-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--rb-space-2); }
.rb-card {
  display: block;
  padding: var(--rb-space-3);
  color: inherit;
  background: var(--rb-console-surface);
  border: 1px solid var(--rb-console-line);
  border-radius: var(--rb-radius-l);
  text-decoration: none;
  transition: border-color 180ms var(--rb-ease), background-color 180ms var(--rb-ease);
}
.rb-card:hover { background: color-mix(in srgb, var(--rb-console-surface) 70%, var(--rb-action)); border-color: var(--rb-action); }
.rb-card h3 { margin: 0 0 6px; color: var(--rb-night-ink); font-size: 1rem; font-weight: 680; }
.rb-card p { margin: 0; color: var(--rb-console-muted); font-size: 14px; line-height: 1.6; }

/* ---- Proof ---- */
.rb-proof { display: grid; grid-template-columns: repeat(4, 1fr); border-block: 1px solid var(--vp-c-divider); }
.rb-fact { padding: var(--rb-space-3) var(--rb-space-2); border-left: 1px solid var(--vp-c-divider); }
.rb-fact:first-child { padding-left: 0; border-left: 0; }
.rb-fact strong, .rb-fact span { display: block; }
.rb-fact strong {
  font-size: 1.9rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  letter-spacing: -.03em;
  line-height: 1.2;
}
.rb-fact span { color: var(--vp-c-text-2); font-size: 13px; }
.rb-proof-note { max-width: 70ch; margin: var(--rb-space-3) 0 0; color: var(--vp-c-text-2); font-size: 14px; }
.rb-proof-note a { color: var(--vp-c-brand-1); }

.rb-next { text-align: center; }
.rb-next .rb-h2 { max-width: 24ch; margin-inline: auto; }
.rb-next .rb-actions { justify-content: center; }

/* ---- Responsive ---- */
@media (max-width: 960px) {
  .rb-split, .rb-grid { grid-template-columns: minmax(0, 1fr); }
  .rb-split { gap: var(--rb-space-6); }
  .rb-rail-grid { grid-template-columns: repeat(2, 1fr); gap: var(--rb-space-2) 0; }
  .rb-rail-item:nth-child(3) { padding-left: 0; border-left: 0; }
}

@media (max-width: 640px) {
  .rb-container { width: calc(100% - var(--rb-space-3)); }
  .rb-hero { padding-block: var(--rb-space-8) var(--rb-space-6); }
  .rb-section { padding-block: var(--rb-space-8); }
  .rb-rail-grid, .rb-proof { grid-template-columns: 1fr; }
  .rb-rail-item, .rb-rail-item:nth-child(3) {
    padding: 10px 0;
    border-left: 0;
    border-bottom: 1px solid var(--vp-c-divider);
  }
  .rb-rail-item:last-child { border-bottom: 0; }
  .rb-fact, .rb-fact:first-child {
    padding: var(--rb-space-2) 0;
    border-left: 0;
    border-bottom: 1px solid var(--vp-c-divider);
  }
  .rb-fact:last-child { border-bottom: 0; }
  .rb-actions { display: grid; }
  .rb-btn { width: 100%; }
  .rb-notes { display: grid; gap: var(--rb-space-1); }
  .rb-code pre { font-size: 12.5px; }
}
</style>
