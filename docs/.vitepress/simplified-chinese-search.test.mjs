import test from 'node:test'
import assert from 'node:assert/strict'
import MiniSearch from 'minisearch'
import fs from 'node:fs'
import {createRequire} from 'node:module'
import path from 'node:path'
import {localSearchMiniSearch,simplifiedChineseSearchMiniSearch} from './local-search.ts'
const restored=value=>typeof value==='function'?new Function(`return (${value.toString()})`)():Array.isArray(value)?value.map(restored):value&&typeof value==='object'?Object.fromEntries(Object.entries(value).map(([k,v])=>[k,restored(v)])):value
const defaults={fields:['title','titles','text'],storeFields:['title','titles']}
function client(docs){const build=new MiniSearch({...defaults,...localSearchMiniSearch.options});build.addAll(docs);return MiniSearch.loadJSON(JSON.stringify(build),{...defaults,...restored(simplifiedChineseSearchMiniSearch).options,searchOptions:{fuzzy:0.2,prefix:true,...simplifiedChineseSearchMiniSearch.searchOptions}})}

test('Simplified Chinese serialized index/query agree for words, width and technical identifiers',()=>{
 const title='规范网址与解析器优先级',titles=['快速入门'];
 const index=client([{id:'/zh-CN/guide/quickstart#resolved',title,titles,text:'HasSEO getUrlForSEO seo_title rankbeam-audit 安装 保存文章 服务提供者 网站地图'}]);
 for(const query of ['规范网址','解析器优先级','服务提供者','网站地图','HasSEO','getUrlForSEO','seo_title','rankbeam-audit']){const result=index.search(query);assert.equal(result[0]?.id,'/zh-CN/guide/quickstart#resolved',query);assert.equal(result[0].title,title);assert.deepEqual(result[0].titles,titles)}
 for(const [a,b]of[['HasSEO','ＨａｓＳＥＯ'],['SEO','ＳＥＯ'],['seo_title','ｓｅｏ＿ｔｉｔｌｅ'],['rankbeam-audit','ｒａｎｋｂｅａｍ－ａｕｄｉｔ']])assert.deepEqual(index.search(a),index.search(b))
 assert.equal(index.search('zzrankbeamnet987').length,0)
})

test('Chinese segmentation is scoped, stateless and matches global punctuation boundaries',()=>{
 const options=restored(simplifiedChineseSearchMiniSearch).options,sample='规范网址与seo_title、rankbeam-audit ＳＥＯ getUrlForSEO';
 const extracted=options.extractField({id:'/zh-CN/guide/a',text:sample},'searchText');
 assert.deepEqual(MiniSearch.getDefault('tokenize')(extracted),options.tokenize(sample))
 for(const id of ['/guide/a','/it/guide/a','/cs/guide/a','/zh-CN-other/a','/zh-TW/a','/zh/a'])assert.equal(options.extractField({id,text:sample},'searchText'),sample)
 assert.equal(options.extractField({id:'/zh-CN/a',title:sample},'title'),sample)
 assert.equal(options.extractField({id:'/tr/a',text:'I İ'},'searchText'),'ı i')
 assert.equal(options.extractField({id:'/el/a',text:'Τίτλος'},'searchText'),'τιτλοσ')
})

test('Chinese searches use actual approved quickstart prose without editing display content',()=>{
 const raw=fs.readFileSync(new URL('../zh-CN/guide/quickstart.md',import.meta.url),'utf8');
 const title=raw.match(/^# (.+)$/m)[1].replace(/\s*\{#[^}]+\}/g,'');
 const prose=raw.replace(/^---\n[\s\S]*?\n---\n/,'').replace(/^```[^\n]*\n[\s\S]*?^```\s*$/gm,'');
 const index=client([{id:'/zh-CN/guide/quickstart',title,titles:[],text:prose}]);
 for(const query of ['服务提供者','规范网址','网站地图','HasSEO','getUrlForSEO','计算得出的回退值'])assert.equal(index.search(query)[0]?.id,'/zh-CN/guide/quickstart',query)
 assert.equal(index.search('规范网址')[0].title,title)
})

test('Chinese Git-date display uses the chosen edition and preserves the browser time zone contract',()=>{
 const require=createRequire(import.meta.url),base=path.dirname(require.resolve('vitepress/package.json'));
 const component=fs.readFileSync(path.join(base,'dist/client/theme-default/components/VPDocFooterLastUpdated.vue'),'utf8');
 assert(component.includes('formatOptions?.forceLocale ? lang.value : undefined'));
 const config=fs.readFileSync(new URL('./config.mts',import.meta.url),'utf8');
 assert(config.includes("locale === 'zh-CN' ? {"));assert(config.includes("formatOptions: { forceLocale: true, dateStyle: 'short' as const, timeStyle: 'short' as const }"));
 const parts=Object.fromEntries(new Intl.DateTimeFormat('zh-CN',{dateStyle:'short',timeStyle:'short',timeZone:'Asia/Shanghai'}).formatToParts(new Date('2026-09-12T00:30:00Z')).map(p=>[p.type,p.value]));
 assert.equal(parts.year,'2026');assert.equal(parts.month,'9');assert.equal(parts.day,'12');assert.equal(Number(parts.hour),8);assert.equal(parts.minute,'30');
})
