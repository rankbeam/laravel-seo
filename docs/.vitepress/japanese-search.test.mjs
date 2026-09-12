import test from 'node:test'
import assert from 'node:assert/strict'
import MiniSearch from 'minisearch'
import fs from 'node:fs'
import {createRequire} from 'node:module'
import path from 'node:path'
import {localSearchMiniSearch,japaneseSearchMiniSearch} from './local-search.ts'
const restored=value=>typeof value==='function'?new Function(`return (${value.toString()})`)():Array.isArray(value)?value.map(restored):value&&typeof value==='object'?Object.fromEntries(Object.entries(value).map(([k,v])=>[k,restored(v)])):value
const defaults={fields:['title','titles','text'],storeFields:['title','titles']}
function client(docs){const build=new MiniSearch({...defaults,...localSearchMiniSearch.options});build.addAll(docs);return MiniSearch.loadJSON(JSON.stringify(build),{...defaults,...restored(japaneseSearchMiniSearch).options,searchOptions:{fuzzy:0.2,prefix:true,...japaneseSearchMiniSearch.searchOptions}})}
test('Japanese serialized build and client preserve word/width/ASCII parity and display titles',()=>{
 const title='正規URLとリゾルバーの優先順位',titles=['クイックスタート'];
 const index=client([{id:'/ja/guide/quickstart#resolved',title,titles,text:'HasSEO getUrlForSEO seo_title rankbeam-audit インストール 保存済みの記事 サービスプロバイダー'}]);
 for(const query of ['正規URL','リゾルバーの優先順位','サービスプロバイダー','HasSEO','getUrlForSEO','seo_title','rankbeam-audit','ｲﾝｽﾄｰﾙ']){const result=index.search(query);assert.equal(result[0]?.id,'/ja/guide/quickstart#resolved',query);assert.equal(result[0].title,title);assert.deepEqual(result[0].titles,titles)}
 for(const [a,b] of [['HasSEO','ＨａｓＳＥＯ'],['インストール','ｲﾝｽﾄｰﾙ'],['seo_title','ｓｅｏ＿ｔｉｔｌｅ']])assert.deepEqual(index.search(a),index.search(b))
 assert.equal(index.search('zzrankbeamnet987').length,0)
})
test('Japanese date configuration uses the installed theme locale contract without changing other editions',()=>{
 const require=createRequire(import.meta.url),base=path.dirname(require.resolve('vitepress/package.json'));
 const component=fs.readFileSync(path.join(base,'dist/client/theme-default/components/VPDocFooterLastUpdated.vue'),'utf8');
 assert(component.includes('formatOptions?.forceLocale ? lang.value : undefined'));
 const config=fs.readFileSync(new URL('./config.mts',import.meta.url),'utf8');
 assert(config.includes("locale === 'ja' ? {"));assert(config.includes("formatOptions: { forceLocale: true, dateStyle: 'short' as const, timeStyle: 'short' as const }"));
 const date=new Date('2026-09-12T00:30:00Z');assert.equal(new Intl.DateTimeFormat('ja',{dateStyle:'short',timeStyle:'short',timeZone:'Asia/Tokyo'}).format(date),'2026/09/12 9:30');
})
test('Japanese extraction is scoped, stateless, and matches serialized query tokens including punctuation',()=>{
 const options=restored(japaneseSearchMiniSearch).options;
 const sample='正規URLとseo_title、rankbeam-audit、ｲﾝｽﾄｰﾙ ＳＥＯ getUrlForSEO';
 const extracted=options.extractField({id:'/ja/guide/a',text:sample},'searchText');
 assert.deepEqual(MiniSearch.getDefault('tokenize')(extracted),options.tokenize(sample))
 for(const id of ['/guide/a','/it/guide/a','/cs/guide/a','/jargon/a'])assert.equal(options.extractField({id,text:sample},'searchText'),sample)
 assert.equal(options.extractField({id:'/ja/a',title:sample},'title'),sample)
 assert.equal(options.extractField({id:'/tr/a',text:'I İ'},'searchText'),'ı i')
 assert.equal(options.extractField({id:'/el/a',text:'Τίτλος'},'searchText'),'τιτλοσ')
})
