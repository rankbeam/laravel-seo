<script setup lang="ts">
import { withBase } from 'vitepress'

const groups = [
  { title: 'Render your pages', links: [
    ['Blade templates', '/guide/blade', 'Render the head from a model.'],
    ['Inertia & JSON APIs', '/guide/inertia-json', 'Pass resolved metadata to your frontend.'],
    ['Livewire', '/guide/livewire', 'Keep metadata in sync with navigation.'],
    ['Filament fields', '/guide/filament', 'Give editors SEO fields and previews.'],
  ]},
  { title: 'Configure the output', links: [
    ['Resolver precedence', '/concepts/resolver-precedence', 'Understand which value wins.'],
    ['JSON-LD schema', '/guide/schema', 'Build a linked schema graph.'],
    ['Sitemaps', '/guide/sitemaps', 'Register models and generate XML.'],
    ['Multilingual content', '/guide/multilingual', 'Resolve metadata for each language.'],
  ]},
  { title: 'Check and maintain', links: [
    ['Free SEO audit', '/guide/audit', 'Check your rendered pages from Artisan.'],
    ['AI crawler controls', '/guide/ai-crawlers', 'Choose and publish a robots policy.'],
    ['Install Pro', '/pro/installation', 'Add scans, redirects and 404 monitoring.'],
    ['Production setup', '/pro/production', 'Configure queues, scheduling and retention.'],
  ]},
]
</script>

<template>
  <div class="docs-start">
    <header class="start-intro">
      <p class="start-label">Rankbeam documentation</p>
      <h1>Add SEO to your Laravel app.</h1>
      <p>Start with the free MIT core for metadata, canonical URLs, JSON-LD and sitemaps. Pro adds monitoring and operations when you need them.</p>
      <a class="start-button" :href="withBase('/guide/quickstart')">Install and render your first tags →</a>
      <p class="start-requirements">Laravel 11–13 · PHP 8.2+ (8.3+ on Laravel 13)</p>
    </header>
    <div class="start-groups">
      <section v-for="group in groups" :key="group.title">
        <h2>{{ group.title }}</h2>
        <ul>
          <li v-for="[title, url, description] in group.links" :key="url">
            <a :href="withBase(url)">{{ title }} <span aria-hidden="true">→</span></a>
            <p>{{ description }}</p>
          </li>
        </ul>
      </section>
    </div>
    <aside class="start-migration">
      <h2>Moving an existing site?</h2>
      <p>Keep your metadata when moving from <a :href="withBase('/guide/migrate-from-wordpress')">WordPress</a> or <a :href="withBase('/guide/migrate-from-other-packages')">another Laravel SEO package</a>. Start with a dry run.</p>
    </aside>
  </div>
</template>

<style scoped>
.docs-start { max-width: 1120px; margin: 0 auto; padding: 56px 32px 72px; color: var(--vp-c-text-1); }
.start-intro { max-width: 720px; }
.start-label { color: var(--vp-c-text-2); font-size: 14px; font-weight: 600; margin: 0 0 12px; }
h1 { font-size: clamp(32px, 4vw, 46px); line-height: 1.15; letter-spacing: -.035em; font-weight: 650; margin: 0 0 20px; }
.start-intro > p:not(.start-label) { font-size: 18px; line-height: 1.7; color: var(--vp-c-text-2); }
a { color: var(--vp-c-brand-1); text-underline-offset: 4px; }
a:hover { text-decoration: underline; }
.start-button { display: inline-block; margin-top: 24px; padding: 13px 20px; border-radius: 8px; background: var(--vp-button-brand-bg); color: var(--vp-button-brand-text); font-size: 15px; font-weight: 600; }
.start-intro p.start-requirements { font-size: 13px; margin-top: 16px; }
.start-groups { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 40px; margin-top: 48px; padding-top: 32px; border-top: 1px solid var(--vp-c-divider); }
h2 { font-size: 19px; font-weight: 650; margin-bottom: 24px; }
ul { list-style: none; padding: 0; margin: 0; }
li { margin-bottom: 24px; }
li a { font-size: 16px; font-weight: 600; }
li p { font-size: 14px; line-height: 1.6; color: var(--vp-c-text-2); margin-top: 5px; }
.start-migration { border-top: 1px solid var(--vp-c-divider); padding-top: 28px; margin-top: 20px; }
.start-migration h2 { margin-bottom: 8px; }
.start-migration p { max-width: 720px; line-height: 1.7; color: var(--vp-c-text-2); }
@media (max-width: 760px) { .docs-start { padding: 32px 24px 48px; } .start-groups { grid-template-columns: 1fr; gap: 24px; margin-top: 32px; } .start-button { line-height: 1.5; } }
</style>
