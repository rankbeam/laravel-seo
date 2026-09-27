"""Subset Google Fonts' OFL Noto CJK TrueType sources for these report catalogs.

Run with --source-dir pointing to NotoSansJP.ttf, NotoSansSC.ttf and
NotoSansKR.ttf (variable [wght] TTFs from google/fonts, not CFF TTC files).
The font source URLs and hashes are recorded in fonts/sources.json.
"""
import argparse
import hashlib
import json
import shutil
from pathlib import Path
from fontTools import subset
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

ROOT = Path(__file__).resolve().parent
parser = argparse.ArgumentParser()
parser.add_argument('--source-dir', type=Path, required=True)
args = parser.parse_args()
sources = []
for locale, family in [('ja','NotoSansJP'),('zh-CN','NotoSansSC'),('ko','NotoSansKR')]:
    catalog = (ROOT/'locales'/f'{locale}.json').read_text('utf-8')
    # All ASCII identifiers, targets and numeric labels may be emitted by the renderer.
    chars = set(map(ord, catalog)) | set(range(32,127))
    source = args.source_dir / (family+'.ttf')
    for weight, suffix in [(400,'Regular'),(700,'Bold')]:
        font = TTFont(source)
        font = instantiateVariableFont(font, {'wght':weight}, inplace=True)
        # Variable source defaults are named Thin. ReportLab deduplicates by the
        # PostScript face name, so static weights MUST have distinct identities.
        names = {1: family+' Report', 2: suffix, 4: family+' Report '+suffix,
                 6: family+'Report-'+suffix, 16: family+' Report', 17: suffix}
        for record in font['name'].names:
            if record.nameID in names:
                record.string = names[record.nameID]
        options = subset.Options()
        options.name_IDs = ['*']
        options.name_legacy = True
        options.name_languages = ['*']
        sub = subset.Subsetter(options=options)
        sub.populate(unicodes=chars)
        sub.subset(font)
        font.save(ROOT/'fonts'/f'{family}-{suffix}.ttf')
    shutil.copyfile(args.source_dir/f'OFL-{family}.txt', ROOT/'fonts'/f'OFL-{family}.txt')
    sources.append({'family':family,'url':f'https://github.com/google/fonts/blob/main/ofl/{family.lower()}/{family}%5Bwght%5D.ttf',
        'source_sha256':hashlib.sha256(source.read_bytes()).hexdigest(), 'weights':[400,700],
        'subset':'catalog characters plus printable ASCII', 'catalog_sha256':hashlib.sha256(catalog.encode()).hexdigest()})
(ROOT/'fonts/sources.json').write_text(json.dumps(sources,indent=2)+'\n','utf-8')
print('Prepared six embedded CJK font subsets.')
