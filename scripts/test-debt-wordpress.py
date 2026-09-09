#!/usr/bin/env python3
from pathlib import Path
import os,subprocess,shutil,json,sys
import argparse
parser=argparse.ArgumentParser(description='Fresh synthetic WordPress fixtures under the BVM disposable database supervisor.')
for option in ['evidence','php','mysql','wp-cli','core','addons','label']:parser.add_argument('--'+option,required=True)
parser.add_argument('--event-tickets',action='store_true',help='Explicit native Event Tickets / Event Tickets Plus integration fixture')
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
profile=E/(run_number+'-containment.sb')
profile.write_text('(version 1)\n(allow default)\n(deny network*)\n(allow network-outbound (literal '+json.dumps(str(root.parent/'mysql.sock'))+'))\n(deny file-write*)\n(allow file-write* (subpath '+json.dumps(str(root))+') (subpath '+json.dumps(str(E))+') (literal "/dev/null"))\n')
env={**os.environ,'BVM_STAFFING_TEST_ROOT':str(root),'BVM_GOOGLE_FAKE':str(private_temp/'google-fake.json'),'BVM_GOOGLE_FIXTURE_ROOT':str(private_temp),'BVM_CALENDAR_EVIDENCE':str(E)}
results=[]
def run(name,args,extra=None):
 if args[0] == php:
  args=['/usr/bin/sandbox-exec','-f',str(profile),*args]
 r=subprocess.run(args,env={**env,**(extra or {})},capture_output=True,timeout=180)
 (E/(run_number+'-'+name+'.stdout')).write_bytes(r.stdout);(E/(run_number+'-'+name+'.stderr')).write_bytes(r.stderr)
 results.append({'test':name,'command':args,'exit':r.returncode,'status':'PASS' if r.returncode==0 else 'FAIL','stdout':run_number+'-'+name+'.stdout','stderr':run_number+'-'+name+'.stderr'})
 (E/(run_number+'-results.json')).write_text(json.dumps(results,indent=2)+'\n')
 print(name,r.returncode,r.stdout.decode(errors='replace')[-250:],flush=True)
 if r.returncode:raise RuntimeError(name+' failed: '+r.stderr.decode(errors='replace')[-1600:])
def cli(name,*args):run(name,[php,'-d','memory_limit=512M',wp,'--path='+str(root),*args])
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
add_filter('pre_http_request',static function($pre,$args,$url){file_put_contents(getenv('BVM_BOUNDARY_LOG'),json_encode(['blocked_http'=>$url])."\n",FILE_APPEND);return new WP_Error('disposable_http_blocked','No test transport');},PHP_INT_MAX,3);
add_filter('pre_wp_mail',static function(){file_put_contents(getenv('BVM_BOUNDARY_LOG'),json_encode(['intercepted_mail'=>true])."\\n",FILE_APPEND);return false;},PHP_INT_MAX);
""")
 env['BVM_BOUNDARY_LOG']=str(E/(run_number+'-http-blocked.jsonl'))
 (root/'wp-config.php').write_text("""<?php
 define('DB_NAME','bvm_integration_source');define('DB_USER','root');define('DB_PASSWORD','');define('DB_HOST','localhost:/private/tmp/bvm-authority-integration-20260906/runtime/mysql.sock');define('DB_CHARSET','utf8mb4');define('DB_COLLATE','');
 define('DISABLE_WP_CRON',true);define('WP_HTTP_BLOCK_EXTERNAL',true);define('WP_DEBUG',false);define('WP_CACHE',false);define('WP_ENVIRONMENT_TYPE','local');define('AUTOMATIC_UPDATER_DISABLED',true);define('WP_AUTO_UPDATE_CORE',false);define('DISALLOW_FILE_MODS',true);define('WP_MEMORY_LIMIT','512M');
 $table_prefix='wp_';if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');require_once ABSPATH.'wp-settings.php';
""")
 run('create-db',[mysql,'--no-defaults','--socket='+env['BVM_DISPOSABLE_DB_SOCKET'],'-uroot','-e','CREATE DATABASE bvm_integration_source CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'])
 cli('install','core','install','--url=http://127.0.0.1:8791','--title=Synthetic BVM regression fixture','--admin_user=fixture_admin','--admin_password=disposable-only','--admin_email=fixture@example.invalid','--skip-email','--quiet')
 cli('theme','theme','activate','minimal','--quiet')
 addons=['woocommerce','the-events-calendar']+(['event-tickets','event-tickets-plus'] if args.event_tickets else [])
 for slug in addons:
  (content/'plugins'/slug).symlink_to(Path(args.addons).resolve()/slug)
 (content/'plugins/backstage-venue-manager').symlink_to(repo)
 cli('activate-bvm','plugin','activate','backstage-venue-manager','--quiet')
 cli('activate-prerequisites','plugin','activate',*addons,'--quiet')
 cli('install-commerce','eval','if(class_exists("WC_Install")) WC_Install::install();','--quiet')
 run('install-staffing',[php,'-d','memory_limit=512M',str(repo/'tests/staffing-lifecycle/bootstrap.php')],{'BVM_STAFFING_INSTALL':'1'})
 run('gate',[php,str(repo/'tests/staffing-lifecycle/preflight-no-mutation.php')])
 cli('http-probe','eval',"$r=wp_remote_get('https://bvm-disposable.invalid/blocked');if(!is_wp_error($r)||$r->get_error_code()!=='disposable_http_blocked')throw new RuntimeException('HTTP escaped');echo 'HTTP blocked before transport';",'--quiet')
 for name in args.tests:
  try: run(name.replace('/','-'),[php,str(repo/('tests/'+name+'.php'))],{'VMS_TEST_WP_LOAD':str(root/'wp-load.php')})
  except RuntimeError as error: print(str(error),flush=True)

finally:
 residue=[str(p.relative_to(root/'owned-temp')) for p in (root/'owned-temp').rglob('*') if p.is_file()] if (root/'owned-temp').exists() else []
 if root.exists():shutil.rmtree(root)
 if private_temp.exists():shutil.rmtree(private_temp)
 (E/(run_number+'-fixture-cleanup.json')).write_text(json.dumps({'temporary_residue_before_runner_cleanup':residue,'wordpress_tree_absent':not root.exists(),'private_fixture_directory_absent':not private_temp.exists(),'database_cleanup':'separate supervisor receipt'},indent=2)+'\n')

if any(r['exit'] for r in results):sys.exit(1)
