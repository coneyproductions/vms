#!/usr/bin/env python3
import os,sys,json,subprocess,shutil,time,re,signal
from pathlib import Path
import argparse
parser=argparse.ArgumentParser(description='Contain and inventory BVM standalone tests; every failure remains a failing exit status.')
parser.add_argument('--evidence',required=True,type=Path)
parser.add_argument('--php',required=True)
parser.add_argument('--label',required=True)
parser.add_argument('--reverse',action='store_true')
parser.add_argument('--frozen-zip',type=Path,help='Explicit read-only qualified WordPress.org ZIP for integrity test')
parser.add_argument('--timezone',default='UTC')
parser.add_argument('tests',nargs='*')
args=parser.parse_args()
R=Path(__file__).resolve().parents[1];W=R.parents[1];E=args.evidence.resolve();PHP=str(Path(args.php).resolve())
if not str(W).startswith('/private/tmp/bvm-test-debt-') or R.parent.name!='packages':
 raise SystemExit('Use a dedicated /private/tmp/bvm-test-debt-*/packages worktree with owned sibling source fixtures. Never run this against normal-local.')
label=args.label
if not re.fullmatch(r'[a-zA-Z0-9_-]+',label):raise SystemExit('Invalid evidence label')
dest=E/label;dest.mkdir(parents=True,exist_ok=False)
profile=dest/'containment.sb'
profile.write_text('(version 1)\n(allow default)\n(deny network*)\n(deny file-write*)\n(allow file-write* (subpath '+json.dumps(str(W))+') (subpath '+json.dumps(str(dest))+') (literal "/dev/null"))\n(deny file-write* (subpath '+json.dumps(str(R/'.git'))+'))\n')
results=[]; selected=set(args.tests)
routes=json.loads((R/'tests/fixtures/test-debt-routes.json').read_text())
unknown=selected-{p.stem for p in (R/'tests').glob('*.php')}
if unknown:raise SystemExit('Unknown tests: '+', '.join(sorted(unknown)))
for test in sorted((R/'tests').glob('*.php'),reverse=args.reverse):
 if selected and test.stem not in selected:continue
 reason=routes.get(test.name)
 if reason:results.append({'test':test.name,'status':'NOT_IN_STANDALONE_SET','reason':reason});continue
 temp=dest/('tmp-'+test.stem);temp.mkdir(exist_ok=False)
 env={k:v for k,v in os.environ.items() if not k.startswith(('VMS_TEST_','BVM_','WP_'))}
 env.update(TMPDIR=str(temp),TMP=str(temp),TEMP=str(temp),GIT_OPTIONAL_LOCKS='0',PATH=str(Path(PHP).parent)+os.pathsep+os.environ['PATH'])
 if test.name == 'financial-investor-semantics.php':
  env['BVM_FINANCIAL_INVESTOR_CANDIDATE']=str(W/'investor-financial-candidate.php')
 cmd=['/usr/bin/sandbox-exec','-f',str(profile),PHP,'-d','memory_limit=512M','-d','date.timezone='+args.timezone,str(test)]
 if test.name == 'check-package-integrity.php':
  import hashlib
  if not args.frozen_zip or not args.frozen_zip.is_file(): raise SystemExit('--frozen-zip is required for package integrity')
  if hashlib.sha256(args.frozen_zip.read_bytes()).hexdigest()!='2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3': raise SystemExit('Qualified ZIP hash mismatch')
  cmd.append(str(args.frozen_zip.resolve()))
 start=time.monotonic()
 p=subprocess.Popen(cmd,cwd=R,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,start_new_session=True)
 try:
  out,err=p.communicate(timeout=300 if test.stem.startswith(('public-release-', 'release-compatibility-', 'g14-g15-')) else 90);rc=p.returncode
 except subprocess.TimeoutExpired:
  os.killpg(p.pid,signal.SIGKILL);out,err=p.communicate();rc=124;err+=b'\nTIMEOUT: owned process group terminated'
 # Descendants must not survive a standalone test, even if their parent exits successfully.
 try:
  os.killpg(p.pid,0)
 except ProcessLookupError:
  pass
 else:
  os.killpg(p.pid,signal.SIGKILL);rc=125;err+=b'\nFAIL: lingering owned descendants terminated'
 (dest/(test.stem+'.stdout')).write_bytes(out);(dest/(test.stem+'.stderr')).write_bytes(err)
 residue=[str(x.relative_to(temp)) for x in temp.rglob('*') if x.is_file() or x.is_symlink()]
 row={'test':test.name,'status':'PASS' if rc==0 else 'FAIL','exit':rc,'command':cmd,'seconds':round(time.monotonic()-start,3),'residue_files':residue,'environment':{'PHP':PHP,'timezone':args.timezone,'network':'OS denied','database':'none; OS denies sockets','temp':str(temp)},'summary':(out+err).decode(errors='replace')[-2200:]}
 results.append(row)
 # Entire path was created by this runner and is isolated for this test.
 shutil.rmtree(temp);row['runner_cleanup_verified']=not temp.exists()
 if rc:print(test.name,rc,row['summary'].splitlines()[0:3],flush=True)
 (dest/'results.json').write_text(json.dumps(results,indent=2)+'\n')
(dest/'results.json').write_text(json.dumps(results,indent=2)+'\n')
print('TOTAL',len(results),'PASS',sum(r['status']=='PASS' for r in results),'FAIL',sum(r['status']=='FAIL' for r in results),'OTHER',sum(r['status']=='NOT_IN_STANDALONE_SET' for r in results),flush=True)

if any(r['status']=='FAIL' for r in results):sys.exit(1)
