"""Export only a verified Git snapshot manifest; never deploy or bootstrap WordPress."""
from pathlib import Path, PurePosixPath
import argparse, hashlib, json, subprocess, zipfile
p=argparse.ArgumentParser()
p.add_argument('--repo',default='.')
p.add_argument('--ref',required=True)
p.add_argument('--prefix',default='')
p.add_argument('--manifest',required=True)
p.add_argument('--slug',required=True)
p.add_argument('--output',required=True)
p.add_argument('--expected-zip-sha256',required=True)
a=p.parse_args()
repo=Path(a.repo).resolve();manifest=json.loads(Path(a.manifest).read_text());out=Path(a.output).resolve()
assert not out.exists(),'Output must be a new directory'
prefix=a.prefix.strip('/')
assert not prefix or ('..' not in PurePosixPath(prefix).parts and not prefix.startswith('/'))
def git(*args):return subprocess.check_output(['git','-C',str(repo),*args])
commit=git('rev-parse',a.ref+'^{commit}').decode().strip()
entries=manifest['files']
assert hashlib.sha256(json.dumps(entries,separators=(',',':'),ensure_ascii=False).encode()).hexdigest()==manifest['tree_sha256']
objects={}
for row in entries:
 rel=row['path'];parts=PurePosixPath(rel).parts
 assert parts and '..' not in parts and not rel.startswith('/')
 tracked=(prefix+'/'if prefix else '')+rel
 mode=git('ls-tree',commit,'--',tracked).decode().split()[0]
 assert mode in ('100644','100755'),'Only ordinary tracked files may be exported'
 data=git('show',commit+':'+tracked)
 assert len(data)==row['bytes'] and hashlib.sha256(data).hexdigest()==row['sha256'],rel
 objects[rel]=data
assert len(objects)==len(entries),'Duplicate manifest path'
# Reject obsolete/unmanifested runtime files, while keeping repo-only material separate.
paths=git('ls-tree','-r','--name-only',commit,'--',prefix or '.').decode().splitlines()
if prefix:runtime={x[len(prefix)+1:]for x in paths}
else:
 runtime={x for x in paths if x.split('/')[0]in ('admin','assets','includes','artifacts') or ('/'not in x and (x.endswith('.php')or x in ('LICENSE.txt','readme.txt','vms-build.txt')))}
assert runtime==set(objects),'Unexpected or missing runtime files'
out.mkdir(parents=True)
source=out/a.slug;source.mkdir()
for rel,data in objects.items():
 target=source/rel;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
zp=out/(a.slug+'.zip')
with zipfile.ZipFile(zp,'x',compression=zipfile.ZIP_DEFLATED,compresslevel=9)as z:
 for row in entries:
  info=zipfile.ZipInfo(a.slug+'/'+row['path'],(2026,11,9,0,0,0));info.create_system=3;info.external_attr=0o100644<<16;info.compress_type=zipfile.ZIP_DEFLATED
  z.writestr(info,objects[row['path']],compresslevel=9)
digest=hashlib.sha256(zp.read_bytes()).hexdigest()
assert digest==a.expected_zip_sha256,'Packaging differs from the retained immutable artifact; do not substitute this build'
with zipfile.ZipFile(zp)as z:assert z.testzip()is None
print(json.dumps(dict(commit=commit,prefix=prefix,files=len(entries),tree_sha256=manifest['tree_sha256'],zip_sha256=digest,exact_retained_zip_reproduction=True)))
