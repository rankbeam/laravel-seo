"""Check published samples, provenance, translations and download destinations."""
import hashlib
import json
import re
import unicodedata
from pathlib import Path
import fitz
from pypdf import PdfReader
from fontTools.ttLib import TTFont

ROOT = Path(__file__).resolve().parent
REPO = ROOT.parents[1]
PUBLIC = REPO/'docs/public/pro-walkthrough'
LOCALES = ['en','it','de','fr','es','pt-BR','nl','tr','pl','ru','cs','el','ja','zh-CN','ko']
catalogs = {locale:json.loads((ROOT/'locales'/f'{locale}.json').read_text('utf-8')) for locale in LOCALES}
manifest = json.loads((PUBLIC/'report-manifest.json').read_text('utf-8'))
assert [r['locale'] for r in manifest['reports']] == LOCALES

def structure(value):
    if isinstance(value, dict):
        return {k:structure(v) for k,v in value.items()}
    if isinstance(value,list):
        return [structure(v) for v in value]
    assert isinstance(value,str) and value.strip()
    return 'string'

def normalized(value):
    return re.sub(r'\s+', '', unicodedata.normalize('NFC',value))

digests = set()
for family in ['NotoSans','NotoSansJP','NotoSansSC','NotoSansKR']:
    faces = [TTFont(ROOT/'fonts'/f'{family}-{weight}.ttf')['name'].getDebugName(6) for weight in ['Regular','Bold']]
    assert len(set(faces))==2,(family,'static font weights share an identity')
for record in manifest['reports']:
    locale = record['locale']
    catalog = catalogs[locale]
    assert structure(catalog)==structure(catalogs['en']),locale
    path = PUBLIC/record['pdf']
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    assert digest==record['sha256'] and path.stat().st_size==record['bytes'],locale
    assert hashlib.sha256((ROOT/'locales'/f'{locale}.json').read_bytes()).hexdigest()==record['catalog_sha256'],locale
    digests.add(digest)
    assert PdfReader(path).trailer['/Root']['/Lang']==locale,locale
    with fitz.open(path) as doc:
        assert len(doc)==2,locale
        embedded = {font[3] for page in doc for font in page.get_fonts() if font[2]=='TrueType'}
        assert any('Regular' in name for name in embedded) and any('Bold' in name for name in embedded),(locale,'font weights not embedded')
        text = normalized(''.join(page.get_text() for page in doc))
        for key in ['title','score','aiScore','fixBody','findings','inventoryNote','methodBody','scope']:
            assert normalized(catalog[key]) in text,(locale,key)
        for page in doc:
            for block in page.get_text('dict')['blocks']:
                for line in block.get('lines',[]):
                    for span in line['spans']:
                        x0,y0,x1,y1 = span['bbox']
                        assert 38<=x0<x1<=page.rect.width-38,(locale,'horizontal bounds',span['text'])
                        assert 20<=y0<y1<=page.rect.height-18,(locale,'vertical bounds',span['text'])
        prefix = '' if locale=='en' else locale+'/'
        links = {link['uri'] for link in doc[1].get_links()}
        assert links=={f'https://docs.rankbeam.dev/{prefix}pro/walkthrough',f'https://docs.rankbeam.dev/{prefix}pro/scoring'},locale
    assert (path.parent/'report-preview.png').is_file(),locale
    for name in ['reports','walkthrough']:
        markdown = (REPO/'docs'/prefix/'pro'/f'{name}.md').read_text('utf-8')
        assert '/pro-walkthrough/'+record['pdf'] in markdown,(locale,name)
        assert '98 KB' not in markdown,(locale,name)

assert len(digests)==15,'Different editions unexpectedly share identical PDF bytes'
original = PUBLIC/'archive/merchant-demo-report-2026-09-09.pdf'
assert hashlib.sha256(original.read_bytes()).hexdigest()=='458c89c44e82e3b8211f8baeb8d558d401f506353264c86a0a8c26901574fdb4'
print('PASS: 15 distinct localized PDFs / 30 pages; catalog parity, searchable copy, language metadata, geometry, links, previews, hashes and original evidence.')
