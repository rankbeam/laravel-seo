"""Derive validation facts from pinned IANA files. No network access required."""
import argparse
import hashlib
import json
from pathlib import Path

SOURCES = {
    'subtags': ('https://www.iana.org/assignments/language-subtag-registry/language-subtag-registry', 'be21e91b6851f750a7b1a687f11209d46ad5a8471d6b10a1efc8d1dac4c8a926'),
    'extensions': ('https://www.iana.org/assignments/language-tag-extensions-registry/language-tag-extensions-registry', 'fdf7764455c493c245a9b3b5b9cd3938391f0637302c3e943fde86aee652e376'),
}

def records(text):
    for block in text.split('%%')[1:]:
        row = {}
        for line in block.splitlines():
            if ': ' in line and not line.startswith(' '):
                key, value = line.split(': ', 1)
                row.setdefault(key, []).append(value)
        if row:
            yield row

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--subtags', type=Path, required=True)
parser.add_argument('--extensions', type=Path, required=True)
parser.add_argument('--check', action='store_true')
args = parser.parse_args()
texts = {}
provenance = {}
for key, (url, digest) in SOURCES.items():
    raw = getattr(args, key).read_bytes()
    if hashlib.sha256(raw).hexdigest() != digest:
        raise SystemExit(f'{key}: unexpected SHA256; review registry changes before updating the pin')
    texts[key] = raw.decode('utf-8')
    provenance[key] = {'url': url, 'sha256': digest, 'file_date': texts[key].splitlines()[0].split(': ', 1)[1]}
data = {'provenance': provenance, 'language': [], 'script': [], 'region': [], 'variant': [], 'extlang': {}, 'grandfathered': [], 'suppress_script': {}, 'preferred': {'language': {}, 'region': {}, 'script': {}, 'variant': {}, 'tag': {}}}
for row in records(texts['subtags']):
    kind = row['Type'][0]
    code = (row.get('Subtag') or row.get('Tag'))[0].lower()
    if kind in ('language', 'script', 'region', 'variant', 'grandfathered'):
        data[kind].append(code)
    elif kind == 'extlang':
        data[kind][code] = [v.lower() for v in row.get('Prefix', [])]
    if 'Preferred-Value' in row and kind != 'extlang':
        target = kind if kind in data['preferred'] else 'tag'
        data['preferred'][target][code] = row['Preferred-Value'][0].lower()
    if kind == 'language' and 'Suppress-Script' in row:
        data['suppress_script'][code] = row['Suppress-Script'][0]
data['extensions'] = [r['Identifier'][0] for r in records(texts['extensions'])]
for key in ('language', 'script', 'region', 'variant', 'grandfathered'):
    data[key] = ' '.join(sorted(data[key]))
output = Path(__file__).resolve().parents[1] / 'resources/data/language-registry.json'
serialized = json.dumps(data, ensure_ascii=False, sort_keys=True, indent=2) + '\n'
if args.check:
    if output.read_text(encoding='utf-8') != serialized:
        raise SystemExit('Derived registry differs')
else:
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(serialized, encoding='utf-8', newline='\n')
print(f'{output.name}: verified {len(serialized.encode())} bytes')
