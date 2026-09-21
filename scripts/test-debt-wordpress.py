#!/usr/bin/env python3
from pathlib import Path
import os,subprocess,shutil,json,sys
import argparse
import hashlib
import zipfile
parser=argparse.ArgumentParser(description='Fresh synthetic WordPress fixtures under the BVM disposable database supervisor.')
for option in ['evidence','php','mysql','wp-cli','core','addons','label']:parser.add_argument('--'+option,required=True)
parser.add_argument('--event-tickets',action='store_true',help='Explicit native Event Tickets / Event Tickets Plus integration fixture')
parser.add_argument('--companion', action='append', default=[], help='Explicit disposable sibling companion slug to activate')
parser.add_argument('--http-fixture', action='store_true', help='Owned loopback WordPress HTTP service for URL integration tests')
parser.add_argument('--private-storage', action='store_true')
parser.add_argument('--admin-acceptance', action='store_true')
parser.add_argument('--p1-fixtures', action='store_true', help='Run P1 tests with pinned companion archives from retained repository evidence')
parser.add_argument('--minimal', action='store_true', help='Bare WordPress fixture without BVM or third-party plugin activation')
parser.add_argument('tests',nargs='+')
args=parser.parse_args()
E=Path(args.evidence).resolve();E.mkdir(parents=True,exist_ok=True)
repo=Path(__file__).resolve().parents[1]
root=Path('/private/tmp/bvm-authority-integration-20260906/runtime/source-wordpress')
if os.environ.get('BVM_DISPOSABLE_DB_GUARDED')!='1' or not (root.parent/'.staffing-db-guard.lock').is_dir():
 raise SystemExit('Launch only through scripts/lib/bvm-disposable-db.py')
if root.exists():raise SystemExit('Refusing to overwrite an existing disposable WordPress tree')
php=str(Path(args.php).resolve());mysql=str(Path(args.mysql).resolve());wp=str(Path(args.wp_cli).resolve())
run_number=args.label
import re
if not re.fullmatch(r'[a-zA-Z0-9_-]+',run_number):raise SystemExit('Invalid evidence label')
if (E/(run_number+'-results.json')).exists():raise SystemExit('Use a fresh evidence label')
private_temp=E/(run_number+'-owned-temp');private_temp.mkdir(exist_ok=False)
p1_roots={}
if args.p1_fixtures:
 pinned={
  'vms-data-tools':('vms-data-tools-0.5.54.zip','d3dc1d9ed7f74aca0c09c9f8f2602a72de4714e97f6e70b1c231f161f5cae6aa'),
  'vms-agreements':('vms-agreements-0.3.48.zip','dbdb32b93d37b8a52868d14da704e1ea758c29edd093d0caeac7fa7a2477b24b'),
 }
 companion_root=private_temp/'p1-companions';companion_root.mkdir()
 for slug,(filename,expected_sha) in pinned.items():
  archive=repo/'docs/addon-compatibility/artifacts'/filename
  if hashlib.sha256(archive.read_bytes()).hexdigest()!=expected_sha: raise SystemExit('Pinned P1 companion archive mismatch: '+filename)
  destination=companion_root/slug;destination.mkdir()
  with zipfile.ZipFile(archive) as bundle:
   for info in bundle.infolist():
    parts=Path(info.filename).parts
    if not parts or parts[0]!=slug or '..' in parts or info.filename.startswith('/') or (info.external_attr>>16)&0o170000==0o120000:
     raise SystemExit('Unsafe P1 companion archive entry: '+info.filename)
   bundle.extractall(destination)
  p1_roots[slug]=destination/slug
profile=E/(run_number+'-containment.sb')
profile.write_text('(version 1)\n(allow default)\n(deny network*)\n(allow network-outbound (literal '+json.dumps(str(root.parent/'mysql.sock'))+'))\n(deny file-write*)\n(allow file-write* (subpath '+json.dumps(str(root))+') (subpath '+json.dumps(str(E))+') (literal "/dev/null"))\n')
if args.http_fixture:
 profile.write_text(profile.read_text()+'(allow network* (local ip "localhost:8791") (remote ip "localhost:8791"))\n')
http_server=None;http_log=None
env={**{k:v for k,v in os.environ.items() if not k.startswith(('VMS_TEST_', 'WP_', 'WAVE3'))},'BVM_STAFFING_TEST_ROOT':str(root),'BVM_GOOGLE_FAKE':str(private_temp/'google-fake.json'),'BVM_GOOGLE_FIXTURE_ROOT':str(private_temp),'BVM_CALENDAR_EVIDENCE':str(E)}
results=[]
name_counts={}
def run(name,args,extra=None):
 if args[0] == php:
  args=['/usr/bin/sandbox-exec','-f',str(profile),*args]
 name_counts[name]=name_counts.get(name,0)+1
 if name_counts[name]>1: name += '-repeat-'+str(name_counts[name])
 r=subprocess.run(args,env={**env,**(extra or {})},capture_output=True,timeout=180)
 (E/(run_number+'-'+name+'.stdout')).write_bytes(r.stdout);(E/(run_number+'-'+name+'.stderr')).write_bytes(r.stderr)
 results.append({'test':name,'command':args,'exit':r.returncode,'status':'PASS' if r.returncode==0 else 'FAIL','stdout':run_number+'-'+name+'.stdout','stderr':run_number+'-'+name+'.stderr'})
 (E/(run_number+'-results.json')).write_text(json.dumps(results,indent=2)+'\n')
 print(name,r.returncode,r.stdout.decode(errors='replace')[-250:],flush=True)
 if r.returncode:raise RuntimeError(name+' failed: '+r.stderr.decode(errors='replace')[-1600:])
def cli(name,*args):run(name,[php,'-d','memory_limit=512M',wp,'--path='+str(root),*args])
def census(label):
 # Read only the fresh supervisor-owned schema; no normal-site connection exists.
 base=[mysql,'--no-defaults','--socket='+env['BVM_DISPOSABLE_DB_SOCKET'],'-uroot','--batch','--skip-column-names','bvm_integration_source','-e']
 def sql(query):
  return subprocess.check_output(base+[query],env=env,text=True,timeout=30).strip()
 tables=sql('SHOW TABLES').splitlines()
 if any(not re.fullmatch(r'wp_[a-zA-Z0-9_]+', table) for table in tables): raise RuntimeError('Unexpected disposable table name')
 rows=sql(' UNION ALL '.join("SELECT '"+table+"',COUNT(*) FROM `"+table+'`' for table in tables))
 counts={row.split('\t')[0]:int(row.split('\t')[1]) for row in rows.splitlines()}
 state={'schema':'bvm_integration_source','socket':env['BVM_DISPOSABLE_DB_SOCKET'],'table_rows':counts,
        'posts_by_type':sql('SELECT post_type,COUNT(*) FROM wp_posts GROUP BY post_type ORDER BY post_type'),
        'options_and_transients':sql("SELECT IF(option_name LIKE '_transient_%','transient','option'),COUNT(*) FROM wp_options GROUP BY 1 ORDER BY 1"),
        'cron_sha256':sql("SELECT SHA2(option_value,256) FROM wp_options WHERE option_name='cron'")}
 (E/(run_number+'-'+label+'-state.json')).write_text(json.dumps(state,indent=2)+'\n')
try:
 source=Path(args.core).resolve()
 if source==root or not (source/'wp-includes/version.php').is_file():raise RuntimeError('Explicit WordPress core source required')
 shutil.copytree(source,root,ignore=shutil.ignore_patterns('wp-content','wp-config.php','.htaccess','.maintenance','error_log','*.log'))
 (root/'owned-temp').mkdir()
 env.update(TMPDIR=str(root/'owned-temp'),TMP=str(root/'owned-temp'),TEMP=str(root/'owned-temp'))
 content=root/'wp-content';(content/'mu-plugins').mkdir(parents=True);(content/'plugins').mkdir();(content/'themes/minimal').mkdir(parents=True)
 (content/'themes/minimal/style.css').write_text('/* Theme Name: Disposable minimal */')
 (content/'themes/minimal/index.php').write_text('<?php // Disposable.\n')
 (content/'mu-plugins/000-boundary.php').write_text("""<?php
add_filter('pre_http_request',static function($pre,$args,$url){if(getenv('BVM_OWNED_HTTP')==='1' && $url==='http://127.0.0.1:8791/?vms_external_ticket_proof=1') return $pre;file_put_contents(getenv('BVM_BOUNDARY_LOG'),json_encode(['blocked_http'=>$url])."\n",FILE_APPEND);return new WP_Error('disposable_http_blocked','No test transport');},PHP_INT_MAX,3);
add_filter('pre_wp_mail',static function(){file_put_contents(getenv('BVM_BOUNDARY_LOG'),json_encode(['intercepted_mail'=>true])."\\n",FILE_APPEND);return false;},PHP_INT_MAX);
""")
 if args.http_fixture: env['BVM_OWNED_HTTP']='1'
 env['BVM_BOUNDARY_LOG']=str(E/(run_number+'-http-blocked.jsonl'))
 if args.private_storage:
  (private_temp/'secure').mkdir()
  env['BVM_QUAL_EVIDENCE']=str(private_temp)
 private_config=("define('BVMGR_PRIVATE_STORAGE_ROOT', "+repr(str(private_temp/'secure'))+");define('BVMGR_PRIVATE_STORAGE_WEB_ROOTS', array("+repr(str(root))+"));") if args.private_storage else ''
 admin_config="define('WP_ADMIN', true);" if args.admin_acceptance else ''
 (root/'wp-config.php').write_text("<?php\n"+private_config+admin_config+"""
 define('DB_NAME','bvm_integration_source');define('DB_USER','root');define('DB_PASSWORD','');define('DB_HOST','localhost:/private/tmp/bvm-authority-integration-20260906/runtime/mysql.sock');define('DB_CHARSET','utf8mb4');define('DB_COLLATE','');
 define('DISABLE_WP_CRON',true);define('WP_HTTP_BLOCK_EXTERNAL',true);define('WP_ACCESSIBLE_HOSTS','127.0.0.1,localhost,8.8.8.8,1.1.1.1');define('WP_DEBUG',false);define('WP_CACHE',false);define('WP_ENVIRONMENT_TYPE','local');define('AUTOMATIC_UPDATER_DISABLED',true);define('WP_AUTO_UPDATE_CORE',false);define('DISALLOW_FILE_MODS',true);define('WP_MEMORY_LIMIT','512M');
 $table_prefix='wp_';if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');require_once ABSPATH.'wp-settings.php';
""")
 run('create-db',[mysql,'--no-defaults','--socket='+env['BVM_DISPOSABLE_DB_SOCKET'],'-uroot','-e','CREATE DATABASE bvm_integration_source CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'])
 cli('install','core','install','--url=http://127.0.0.1:8791','--title=Synthetic BVM regression fixture','--admin_user=fixture_admin','--admin_password=disposable-only','--admin_email=fixture@example.invalid','--skip-email','--quiet')
 cli('theme','theme','activate','minimal','--quiet')
 addons=[] if args.minimal else ['woocommerce','the-events-calendar']+(['event-tickets','event-tickets-plus'] if args.event_tickets else [])
 for slug in addons:
  (content/'plugins'/slug).symlink_to(Path(args.addons).resolve()/slug)
 allowed_companions={'backstage-calendar-feeds','drm-calendar-intake','drm-event-router','drm-events-bridge','vms-data-tools','vmsx-weather-risk','vms-commerce-discounts','vms-sponsorships'}
 if not set(args.companion)<=allowed_companions: raise RuntimeError('Companion is outside the explicit fixture allowlist')
 for slug in allowed_companions:
  source=repo.parents[1]/slug
  if source.is_dir(): (content/'plugins'/slug).symlink_to(source)
 if not args.minimal:
  (content/'plugins/backstage-venue-manager').symlink_to(repo)
  cli('activate-bvm','plugin','activate','backstage-venue-manager','--quiet')
  cli('activate-prerequisites','plugin','activate',*addons,'--quiet')
 if args.companion: cli('activate-companions','plugin','activate',*args.companion,'--quiet')
 if not args.minimal:
  cli('install-commerce','eval','if(class_exists("WC_Install")) WC_Install::install();','--quiet')
  run('install-staffing',[php,'-d','memory_limit=512M',str(repo/'tests/staffing-lifecycle/bootstrap.php')],{'BVM_STAFFING_INSTALL':'1'})
  run('gate',[php,str(repo/'tests/staffing-lifecycle/preflight-no-mutation.php')])
 cli('http-probe','eval',"$r=wp_remote_get('https://bvm-disposable.invalid/blocked');if(!is_wp_error($r)||$r->get_error_code()!=='disposable_http_blocked')throw new RuntimeException('HTTP escaped');echo 'HTTP blocked before transport';",'--quiet')
 if args.http_fixture:
  import socket,time
  probe=socket.socket()
  try: probe.bind(('127.0.0.1',8791))
  finally: probe.close()
  http_log=open(E/(run_number+'-http-server.log'),'wb')
  http_server=subprocess.Popen(['/usr/bin/sandbox-exec','-f',str(profile),php,'-d','memory_limit=512M','-S','127.0.0.1:8791','-t',str(root)],env=env,stdout=http_log,stderr=subprocess.STDOUT,start_new_session=True)
  for attempt in range(50):
   if http_server.poll() is not None: raise RuntimeError('Owned HTTP server stopped during startup')
   try:
    with socket.create_connection(('127.0.0.1',8791),timeout=.1): break
   except OSError: time.sleep(.1)
  else: raise RuntimeError('Owned HTTP service did not become ready')
 for index,name in enumerate(args.tests,1):
  census(str(index)+'-before')
  try:
   if name=='wporg-round2-vendor-tax-authorization':
    if not args.p1_fixtures: raise RuntimeError('P1-A requires --p1-fixtures')
    run(name,[php,wp,'--path='+str(root),'--skip-plugins','--skip-themes','eval-file',str(repo/'tests/wporg-round2-vendor-tax-authorization.php')],{'BVM_P1A_DISPOSABLE':'1'})
   elif name=='wporg-round2-webhook-safety':
    if not args.p1_fixtures: raise RuntimeError('P1-B requires --p1-fixtures')
    run(name,[php,wp,'--path='+str(root),'--skip-plugins','--skip-themes','eval-file',str(repo/'tests/wporg-round2-webhook-safety.php')],{'BVM_P1B_DISPOSABLE':'1','BVM_P1B_SOURCE':str(repo)})
   elif name=='wporg-round2-portal-hook-cutover':
    if not args.p1_fixtures: raise RuntimeError('P1-C requires --p1-fixtures')
    for scenario in ('data-tools','agreements','both'):
     run(name+'-'+scenario,[php,wp,'--path='+str(root),'--skip-plugins','--skip-themes','eval-file',str(repo/'tests/wporg-round2-portal-hook-cutover.php')],{'BVM_P1C2_DISPOSABLE':'1','BVM_P1C2_SCENARIO':scenario,'BVM_P1C2_SOURCE':str(repo),'BVM_P1C2_DATA_TOOLS':str(p1_roots['vms-data-tools']),'BVM_P1C2_AGREEMENTS':str(p1_roots['vms-agreements'])})
   elif name=='wporg-round2-admission-qr-privacy':
    if not args.p1_fixtures: raise RuntimeError('P1-D requires --p1-fixtures')
    run(name,[php,wp,'--path='+str(root),'--skip-plugins','--skip-themes','eval-file',str(repo/'tests/wporg-round2-admission-qr-privacy.php')],{'BVM_P1D_DISPOSABLE':'1','BVM_P1D_EVIDENCE':str(E)})
   elif name.endswith('normal-local-acceptance'):
    if not args.admin_acceptance: raise RuntimeError('Admin acceptance requires explicit fixture mode')
    run(name,[php,wp,'--path='+str(root),'--skip-plugins','--skip-themes','eval-file',str(repo/('tests/'+name+'.php'))],{'WAVE3B1_CALENDAR_ACCEPTANCE_MODE':'acceptance','WAVE3B1_CALENDAR_EXPECTED_VERSION':'0.1.4','WAVE3B1_CALENDAR_EXPECTED_TREE_SHA256':'20f2607ec61c62f2d7120cd99b657b46e15bc169f6d92d14f7951540e33fc20e','WAVE3B2_DATA_TOOLS_MODE':'acceptance','WAVE3B2_DATA_TOOLS_EXPECTED_VERSION':'0.5.55','WAVE3B2_DATA_TOOLS_EXPECTED_TREE_SHA256':'8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b'})
   else: run(name.replace('/','-'),[php,str(repo/('tests/'+name+'.php'))],{'VMS_TEST_WP_LOAD':str(root/'wp-load.php')})
  except RuntimeError as error: print(str(error),flush=True)
  finally: census(str(index)+'-after')

finally:
 if http_server is not None:
  import signal
  if http_server.poll() is None: os.killpg(http_server.pid,signal.SIGTERM)
  try: http_server.wait(timeout=5)
  except subprocess.TimeoutExpired: os.killpg(http_server.pid,signal.SIGKILL);http_server.wait(timeout=5)
  if http_log is not None: http_log.close()
  (E/(run_number+'-http-cleanup.json')).write_text(json.dumps({'pid':http_server.pid,'exit':http_server.returncode,'owned_process_reaped':http_server.poll() is not None})+'\n')
 residue=[str(p.relative_to(root/'owned-temp')) for p in (root/'owned-temp').rglob('*') if p.is_file()] if (root/'owned-temp').exists() else []
 if root.exists():shutil.rmtree(root)
 if args.private_storage and private_temp.exists():
  for receipt_file in private_temp.glob('*.json'): shutil.copyfile(receipt_file,E/(run_number+'-'+receipt_file.name))
 if private_temp.exists():shutil.rmtree(private_temp)
 (E/(run_number+'-fixture-cleanup.json')).write_text(json.dumps({'temporary_residue_before_runner_cleanup':residue,'wordpress_tree_absent':not root.exists(),'private_fixture_directory_absent':not private_temp.exists(),'database_cleanup':'separate supervisor receipt'},indent=2)+'\n')

if any(r['exit'] for r in results):sys.exit(1)
