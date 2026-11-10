"""Build the Issue 124 local-only candidate from committed manifest-whitelisted blobs."""
from pathlib import Path
import argparse,hashlib,json,subprocess,zipfile
p=argparse.ArgumentParser();p.add_argument('--ref',required=True);p.add_argument('--output',required=True);a=p.parse_args()
repo=Path.cwd();out=Path(a.output).resolve();assert not out.exists(),'Immutable output must not exist'
def git(*args):return subprocess.check_output(['git','-C',str(repo),*args])
def sha(data):return hashlib.sha256(data).hexdigest()
commit=git('rev-parse',a.ref+'^{commit}').decode().strip();base=json.loads(git('show',commit+':docs/checkout-20261109/bvm-quantity-manifest.json'))
assert base['tree_sha256']=='f0ad3fdde68fb066fd4771ec402e6fcc94ea038aabb0e5373586ba17dc9c66b9'
assert sha(json.dumps(base['files'],separators=(',',':'),ensure_ascii=False).encode())==base['tree_sha256']
expected={'assets/css/vms-ticketing-front.css','assets/vms-ticketing-front.js','includes/integrations/ticketing-rules-v2.php','backstage-venue-manager.php','includes/core/registry/constants.php','readme.txt','vms-build.txt'}
entries=[];objects={};changed=[]
for row in base['files']:
 rel=row['path'];mode=git('ls-tree',commit,'--',rel).decode().split()[0];assert mode in ('100644','100755')
 data=git('show',commit+':'+rel);objects[rel]=data
 current={'path':rel,'bytes':len(data),'sha256':sha(data)};entries.append(current)
 if current!=row:changed.append(rel)
assert set(changed)==expected and len(entries)==431
paths=git('ls-tree','-r','--name-only',commit).decode().splitlines()
runtime={x for x in paths if x.split('/')[0]in ('admin','assets','includes','artifacts')or('/'not in x and(x.endswith('.php')or x in('LICENSE.txt','readme.txt','vms-build.txt')))}
assert runtime==set(objects),'Unmanifested or missing runtime file'
version='1.3.19-checkout.20261109.3';slug='backstage-venue-manager'
for rel in ['backstage-venue-manager.php','includes/core/registry/constants.php','readme.txt','vms-build.txt']:assert version.encode()in objects[rel]
manifest={'tree_sha256':sha(json.dumps(entries,separators=(',',':'),ensure_ascii=False).encode()),'files':entries}
out.mkdir(parents=True);zp=out/(slug+'-'+version+'.zip')
with zipfile.ZipFile(zp,'x',compression=zipfile.ZIP_DEFLATED,compresslevel=9)as z:
 for row in entries:
  info=zipfile.ZipInfo(slug+'/'+row['path'],(2026,11,9,0,0,0));info.create_system=3;info.external_attr=0o100644<<16;info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,objects[row['path']],compresslevel=9)
with zipfile.ZipFile(zp)as z:
 assert z.testzip()is None and len(z.namelist())==431
 for row in entries:assert sha(z.read(slug+'/'+row['path']))==row['sha256']
mp=out/(slug+'-'+version+'-manifest.json');mp.write_text(json.dumps(manifest,indent=2)+'\n')
index={'version':version,'source_commit':commit,'baseline_source_checkpoint':'1f5fa9880b23ce035092bffac0a6c4151a765b44','baseline_tree_sha256':base['tree_sha256'],'tree_sha256':manifest['tree_sha256'],'zip_sha256':sha(zp.read_bytes()),'zip':str(zp),'manifest':str(mp),'changed_runtime_files':changed,'runtime_files':431,'unrelated_runtime_files_preserved':424,'deployment_authorized':False}
(out/'release.json').write_text(json.dumps(index,indent=2)+'\n')
for f in out.iterdir():f.chmod(0o444)
print(json.dumps(index,indent=2))
