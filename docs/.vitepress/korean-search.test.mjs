import test from 'node:test'
import assert from 'node:assert/strict'
import MiniSearch from 'minisearch'
import fs from 'node:fs'
import {localSearchMiniSearch,koreanSearchMiniSearch} from './local-search.ts'
const restored=value=>typeof value==='function'?new Function(`return (${value.toString()})`)():Array.isArray(value)?value.map(restored):value&&typeof value==='object'?Object.fromEntries(Object.entries(value).map(([k,v])=>[k,restored(v)])):value
const defaults={fields:['title','titles','text'],storeFields:['title','titles']}
function client(docs){const build=new MiniSearch({...defaults,...localSearchMiniSearch.options});build.addAll(docs);return MiniSearch.loadJSON(JSON.stringify(build),{...defaults,...restored(koreanSearchMiniSearch).options,searchOptions:{fuzzy:0.2,prefix:true,...koreanSearchMiniSearch.searchOptions}})}

test('Korean serialized extraction and query tokenization agree for NFC/NFD and fullwidth identifiers',()=>{
 const title='사이트맵 레지스트리',titles=['빠른 시작'],text='사이트맵을 생성하고 소스를 등록합니다. 표준 URL HasSEO getUrlForSEO seo_title rankbeam-audit';
 for(const storedText of [text,text.normalize('NFD')]){
  const index=client([{id:'/ko/guide/sitemaps',title,titles,text:storedText}]);
  for(const query of ['사이트맵','사이트맵을','소스','소스를','표준 URL','HasSEO','getUrlForSEO','seo_title','rankbeam-audit']){
   const results=index.search(query);assert.equal(results[0]?.id,'/ko/guide/sitemaps',query);assert.equal(results[0].title,title);assert.deepEqual(results[0].titles,titles);assert.deepEqual(index.search(query.normalize('NFD')),results);
  }
  for(const [a,b]of[['HasSEO','ＨａｓＳＥＯ'],['SEO','ＳＥＯ'],['seo_title','ｓｅｏ＿ｔｉｔｌｅ'],['rankbeam-audit','ｒａｎｋｂｅａｍ－ａｕｄｉｔ']])assert.deepEqual(index.search(a),index.search(b));
  assert.equal(index.search('zzrankbeamnet987').length,0);
 }
})

test('Korean normalization is scoped and preserves default punctuation and display fields',()=>{
 const options=restored(koreanSearchMiniSearch).options,sample='사이트맵 ＳＥＯ seo_title rankbeam-audit'.normalize('NFD');
 assert.deepEqual(MiniSearch.getDefault('tokenize')(options.extractField({id:'/ko/a',text:sample},'searchText')),options.tokenize(sample));
 for(const id of ['/guide/a','/it/guide/a','/cs/guide/a','/ko-other/a','/ko-KR/a'])assert.equal(options.extractField({id,text:sample},'searchText'),sample);
 assert.equal(options.extractField({id:'/ko/a',title:sample},'title'),sample);
 assert.equal(options.extractField({id:'/tr/a',text:'I İ'},'searchText'),'ı i');
 assert.equal(options.extractField({id:'/el/a',text:'Τίτλος'},'searchText'),'τιτλοσ');
 assert.notDeepEqual(options.tokenize('사이트 맵'),options.tokenize('사이트맵'),'No invented spacing/morphological equivalence');
})

test('actual parent-staged Korean sitemap content matches noun and observed particle queries',()=>{
 const raw=fs.readFileSync(new URL('../ko/guide/sitemaps.md',import.meta.url),'utf8'),title=raw.match(/^# (.+)$/m)[1].replace(/\s*\{#[^}]+\}/g,'');
 const index=client([{id:'/ko/guide/sitemaps',title,titles:[],text:raw.replace(/^```[^\n]*\n[\s\S]*?^```\s*$/gm,'')}]);
 for(const query of ['사이트맵','사이트맵을','소스','소스를','getUrlForSEO']){assert.equal(index.search(query)[0]?.id,'/ko/guide/sitemaps',query);assert.deepEqual(index.search(query.normalize('NFD')),index.search(query));}
})

test('Korean dates use edition locale with caller time zone preserved',()=>{
 const config=fs.readFileSync(new URL('./config.mts',import.meta.url),'utf8');assert(config.includes("locale === 'ko' ? {"));assert(config.includes("formatOptions: { forceLocale: true, dateStyle: 'short' as const, timeStyle: 'short' as const }"));
 const date=new Date('2026-09-12T00:30:00Z'),options={dateStyle:'short',timeStyle:'short'};
 const parts=zone=>Object.fromEntries(new Intl.DateTimeFormat('ko',{...options,timeZone:zone}).formatToParts(date).map(p=>[p.type,p.value]));
 assert.equal(parts('Asia/Seoul').day,'12');assert.equal(Number(parts('Asia/Seoul').hour),9);assert.equal(parts('America/Los_Angeles').day,'11');assert.equal(date.toISOString(),'2026-09-12T00:30:00.000Z');
})
