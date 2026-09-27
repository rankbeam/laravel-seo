"""Build the public, translated Merchant evidence companion (not the Pro export template)."""
from __future__ import annotations
import argparse
import hashlib
import json
from collections import Counter
from pathlib import Path
from xml.sax.saxutils import escape

import fitz
from fontTools.ttLib import TTFont as InspectFont
from pypdf import PdfReader, PdfWriter
from pypdf.generic import NameObject, TextStringObject
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.platypus import Paragraph

ROOT = Path(__file__).resolve().parent
OUTPUT = ROOT.parents[1] / 'docs/public/pro-walkthrough'
LOCALES = ['en', 'it', 'de', 'fr', 'es', 'pt-BR', 'nl', 'tr', 'pl', 'ru', 'cs', 'el', 'ja', 'zh-CN', 'ko']
W, H = 595.2756, 841.8898
M, CW = 40, W - 80
NAVY, BLUE, INK, MUTED = '#12172D', '#2457D6', '#172139', '#556174'
RULE, PALE, WHITE = '#DCE2EC', '#EEF2FB', '#FFFFFF'
SOURCE = json.loads((ROOT / 'evidence/after.json').read_text('utf-8'))
BEFORE = json.loads((ROOT / 'evidence/before.json').read_text('utf-8'))
SCORES = [BEFORE['summary']['score'], SOURCE['summary']['score']]
ROWS = [row for section in SOURCE['sections'] for row in section['rows']]
COUNTS = Counter(row['Type'] for row in ROWS)
ORDER = ['missing_description', 'title_too_long', 'description_too_long', 'description_too_short', 'title_too_short', 'thin_content']
assert sum(COUNTS.values()) == SOURCE['summary']['issues'] == 19
assert SCORES == [92, 93]

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def strings(value):
    if isinstance(value, str):
        yield value
    elif isinstance(value, dict):
        for child in value.values():
            yield from strings(child)
    elif isinstance(value, list):
        for child in value:
            yield from strings(child)

class Report:
    def __init__(self, locale, data, path):
        self.locale, self.t = locale, data
        family = {'ja': 'NotoSansJP', 'zh-CN': 'NotoSansSC', 'ko': 'NotoSansKR'}.get(locale, 'NotoSans')
        self.regular, self.bold = family + '-Regular', family + '-Bold'
        for name in [self.regular, self.bold]:
            font_path = ROOT / 'fonts' / (name + '.ttf')
            font = InspectFont(font_path)
            required = set(''.join(strings(data)) + 'Rankbeam0123456789/#;.,+-' + ''.join(r['Target'] for r in ROWS)) - {'\n', '\r', '\t'}
            missing = required - set(map(chr, font.getBestCmap()))
            if missing:
                raise ValueError(f'{locale}/{name}: missing glyphs {missing}')
            pdfmetrics.registerFont(TTFont(name, str(font_path)))
        self.c = canvas.Canvas(str(path), pagesize=(W, H), invariant=1, pageCompression=1)
        self.c.setTitle(data['title'] + ' | Rankbeam Merchant')
        self.c.setAuthor('Rankbeam')
        self.c.setSubject(data['scope'])
        self.links = []

    def rect(self, x, y, w, h, fill):
        self.c.setFillColor(colors.HexColor(fill))
        self.c.rect(x, H-y-h, w, h, fill=1, stroke=0)

    def line(self, x, y, x2, color=RULE):
        self.c.setStrokeColor(colors.HexColor(color))
        self.c.setLineWidth(.55)
        self.c.line(x, H-y, x2, H-y)

    def text(self, text, x, y, size=10, color=INK, bold=False):
        self.c.setFillColor(colors.HexColor(color))
        self.c.setFont(self.bold if bold else self.regular, size)
        self.c.drawString(x, H-y-size, str(text))

    def para(self, text, x, y, width, size=10, color=INK, bold=False, max_height=None, leading=None):
        style = ParagraphStyle('report', fontName=self.bold if bold else self.regular,
            fontSize=size, leading=leading or size*1.4, textColor=colors.HexColor(color),
            alignment=TA_LEFT, wordWrap='CJK' if self.locale in ['ja', 'zh-CN', 'ko'] else None,
            splitLongWords=True, spaceAfter=0)
        p = Paragraph(escape(str(text)), style)
        _, height = p.wrap(width, H)
        if max_height is not None and height > max_height + .1:
            raise ValueError(f'{self.locale}: overflow ({height:.1f}>{max_height}) {text[:80]}')
        p.drawOn(self.c, x, H-y-height)
        return height

    def chrome(self, page):
        # The real family mark; use the site's transparent 512px master.
        self.c.drawImage(str(ROOT/'mark-color-512.png'), M, H-49, width=24, height=24, mask='auto')
        self.text('Rankbeam', M+29, 30, 14, NAVY, True)
        self.para(self.t['edition'] + ' / ' + self.t['language'], W-240, 32, 200, 8, MUTED, max_height=22)
        self.line(M, 64, W-M)
        self.line(M, 792, W-M)
        self.text('rankbeam.dev', M, 804, 8, MUTED)
        self.text(f'0{page} / 02', W-M-42, 804, 8, MUTED)

    def first(self):
        t = self.t
        self.chrome(1)
        self.para(t['title'], M, 82, CW, 28, NAVY, True, max_height=42)
        self.para(t['subtitle'], M, 128, CW-160, 10, MUTED, max_height=28)
        self.para(t['date'], W-M-147, 129, 147, 9, MUTED, max_height=26)
        gap = 12
        boxw = (CW-gap)/2
        for i, (label, number, delta, note) in enumerate([
            (t['score'], str(SCORES[1]), t['scoreChange'], t['scoreNote']),
            (t['aiScore'], '50', t['aiChange'], t['aiNote'])
        ]):
            x = M + i*(boxw+gap)
            dark = i == 0
            self.rect(x, 175, boxw, 177, NAVY if dark else PALE)
            self.rect(x, 175, boxw, 3, BLUE)
            self.para(label, x+17, 190, boxw-34, 10, WHITE if dark else NAVY, True, max_height=28)
            self.text(number, x+16, 209, 47, WHITE if dark else NAVY, True)
            self.text('/ 100', x+88, 248, 11, '#BBC6DC' if dark else MUTED)
            self.para(delta, x+17, 272, boxw-34, 9, '#C6D6FF' if dark else BLUE, True, max_height=26)
            self.para(note, x+17, 301, boxw-34, 8.2, '#D5DCEC' if dark else MUTED, max_height=46, leading=11)
        for i, (number, label) in enumerate([('21', t['scanned']), ('1', t['fixed']), ('19', t['open'])]):
            x = M + i*(CW/3)
            self.text(number, x, 369, 25, NAVY, True)
            self.para(label, x, 408, CW/3-15, 9, MUTED, max_height=27)
        self.line(M, 446, W-M)
        self.para(t['comparison'], M, 461, CW, 17, NAVY, True, max_height=27)
        self.para(t['comparisonNote'], M, 491, CW, 9, MUTED, max_height=39)
        self.rect(M+241, 538, 7, 7, '#B8C5DF')
        self.para(t['before'], M+253, 534, 110, 8, MUTED, max_height=22)
        self.rect(M+371, 538, 7, 7, BLUE)
        self.para(t['after'], M+383, 534, 128, 8, MUTED, max_height=22)
        for i, (key, label) in enumerate([('issues',t['openIssues']), ('warning',t['warnings']), ('notice',t['notices'])]):
            y = 560 + 34*i
            self.para(label, M, y, 190, 10, INK, max_height=29)
            for j, value in enumerate([BEFORE['summary'][key], SOURCE['summary'][key]]):
                x = M+241+j*130
                self.rect(x, y+4, 85, 8, '#EEF1F6')
                self.rect(x, y+4, 85*value/21, 8, '#B8C5DF' if j == 0 else BLUE)
                self.text(value, x+94, y, 10, INK, True)
        self.rect(M, 674, CW, 102, '#F4F6FA')
        self.para(t['verified'], M+16, 687, CW-32, 7.5, BLUE, True, max_height=16)
        self.para(t['fixTitle'], M+16, 706, CW-32, 13, NAVY, True, max_height=20)
        self.para(t['fixBody'], M+16, 733, CW-32, 8.5, MUTED, max_height=36, leading=11.5)
        self.c.showPage()

    def second(self):
        t = self.t
        self.chrome(2)
        self.para(t['findings'], M, 82, CW, 25, NAVY, True, max_height=40)
        self.para(t['inventoryNote'], M, 127, CW, 9, MUTED, max_height=39)
        self.rect(M, 177, CW, 24, NAVY)
        self.para(t['check'], M+10, 183, 169, 8, WHITE, True, max_height=14)
        self.para(t['count'], M+196, 183, 69, 8, WHITE, True, max_height=14)
        self.para(t['targets'], M+282, 183, CW-292, 8, WHITE, True, max_height=14)
        for i, kind in enumerate(ORDER):
            y = 201+i*39
            if i%2 == 0:
                self.rect(M, y, CW, 39, '#F4F6FA')
            severity = 'warning' if i < 3 else 'notice'
            self.para(t['types'][kind], M+10, y+5, 174, 9, INK, True, max_height=21, leading=10.5)
            self.para(t[severity], M+10, y+27, 174, 7.2, '#8A521A' if i<3 else MUTED, max_height=10, leading=9)
            self.text(COUNTS[kind], M+207, y+9, 13, NAVY, True)
            targets = '; '.join(row['Target'].replace('http://127.0.0.1:8015', '') or '/' for row in ROWS if row['Type']==kind)
            self.para(targets, M+282, y+5, CW-294, 7.8, MUTED, max_height=33, leading=10.4)
            self.line(M, y+39, W-M)
        self.para(f"{t['critical']}: 0 / {t['warnings']}: 5 / {t['notices']}: 14", M, 448, CW, 8, MUTED, max_height=24)
        self.para(t['next'], M, 481, CW, 17, NAVY, True, max_height=28)
        for i, action in enumerate(t['actions']):
            y = 516+i*47
            self.text(f'0{i+1}', M, y, 10, BLUE, True)
            self.para(action['title'], M+29, y-1, CW-29, 10, NAVY, True, max_height=16)
            self.para(action['body'], M+29, y+16, CW-29, 8.2, MUTED, max_height=32, leading=10.5)
        self.line(M, 665, W-M)
        self.para(t['method'], M, 678, CW, 10, NAVY, True, max_height=17)
        self.para(t['methodBody'] + ' ' + t['scope'], M, 699, CW, 7.6, MUTED, max_height=64, leading=10.1)
        prefix = '' if self.locale == 'en' else self.locale + '/'
        for x, label, destination in [(M,t['source'],'pro/walkthrough'),(M+CW/2+10,t['rubric'],'pro/scoring')]:
            self.para(label, x, 768, CW/2-12, 7.8, BLUE, max_height=15)
            self.c.linkURL('https://docs.rankbeam.dev/'+prefix+destination, (x,H-785,x+CW/2-12,H-767), relative=0)
        self.c.showPage()

    def save(self, path):
        self.c.save()
        reader = PdfReader(path)
        writer = PdfWriter(clone_from=reader)
        writer._root_object[NameObject('/Lang')] = TextStringObject(self.locale)
        writer.write(path)

def build(locale):
    data = json.loads((ROOT / 'locales' / (locale+'.json')).read_text('utf-8'))
    folder = OUTPUT if locale == 'en' else OUTPUT / locale
    folder.mkdir(exist_ok=True, parents=True)
    path = folder / 'merchant-demo-report.pdf'
    report = Report(locale, data, path)
    report.first()
    report.second()
    report.save(path)
    with fitz.open(path) as doc:
        assert len(doc)==2
        assert all(page.get_text().strip() for page in doc)
        doc[0].get_pixmap(matrix=fitz.Matrix(1.7,1.7), alpha=False).save(folder/'report-preview.png')
    return {'locale':locale, 'pdf':str(path.relative_to(OUTPUT)).replace('\\','/'), 'pages':2,
        'bytes':path.stat().st_size, 'sha256':sha(path), 'catalog_sha256':sha(ROOT/'locales'/f'{locale}.json')}

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--locale', choices=LOCALES)
    args = parser.parse_args()
    records = [build(locale) for locale in ([args.locale] if args.locale else LOCALES)]
    if not args.locale:
        (OUTPUT/'report-manifest.json').write_text(json.dumps({'evidence_date':'2026-09-09', 'design_date':'2026-09-27', 'reports':records},ensure_ascii=False,indent=2)+'\n','utf-8')
    print(json.dumps(records,ensure_ascii=False,indent=2))
