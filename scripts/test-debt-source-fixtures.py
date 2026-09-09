#!/usr/bin/env python3
"""Copy explicit authenticated source fixtures into an owned Phase 5B worktree.

No plugins are activated, no database is read, and no source is modified.
The input manifest is a list of {slug, source, files:{relative:sha256}} records.
Use the retained closeout companion-source-fixtures.json as the pinned manifest.
"""
import argparse, hashlib, json, shutil
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--manifest', required=True, type=Path)
parser.add_argument('--receipt', required=True, type=Path)
parser.add_argument('--verify', action='store_true', help='Verify existing copies without writing them')
args = parser.parse_args()
repo = Path(__file__).resolve().parents[1]
owned = repo.parents[1]
if not str(owned).startswith('/private/tmp/bvm-test-debt-') or repo.parent.name != 'packages':
    raise SystemExit('Only an isolated Phase 5B worktree is permitted')
if args.receipt.exists():
    raise SystemExit('A fresh receipt path is required')
records = json.loads(args.manifest.read_text())
receipts = []
for record in records:
    slug = record['slug']
    if Path(slug).name != slug or slug in {'.', '..', 'packages', 'vms', 'backstage-venue-manager'}:
        raise SystemExit('Invalid companion slug')
    source, target = Path(record['source']).resolve(), owned / slug
    expected = record['files']
    def manifest(root):
        result = {}
        for file in root.rglob('*'):
            if '.git' in file.relative_to(root).parts:
                continue
            if file.is_symlink():
                raise RuntimeError('Source fixture rejects symlinks: ' + str(file))
            if file.is_file():
                result[str(file.relative_to(root))] = hashlib.sha256(file.read_bytes()).hexdigest()
        return result
    if not source.is_dir() or manifest(source) != expected:
        raise SystemExit('Explicit source differs from pinned fixture: ' + slug)
    if args.verify:
        if not target.is_dir() or manifest(target) != expected:
            raise SystemExit('Owned fixture mismatch: ' + slug)
    else:
        if target.exists() or target.is_symlink():
            raise SystemExit('Refusing to overwrite a fixture: ' + slug)
        try:
            shutil.copytree(source, target, ignore=shutil.ignore_patterns('.git'))
            if manifest(target) != expected:
                raise RuntimeError('Copy authentication failed')
        except BaseException:
            if target.exists():
                shutil.rmtree(target)
            raise
    if manifest(source) != expected:
        raise SystemExit('Source changed during verification: ' + slug)
    receipts.append({'slug': slug, 'source': str(source), 'fixture': str(target),
                     'file_count': len(expected), 'source_and_copy_authenticated': True})
args.receipt.write_text(json.dumps(receipts, indent=2) + '\n')
print('Verified', len(receipts), 'explicit companion fixtures')
