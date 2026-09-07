#!/usr/bin/env python3
"""Real process/signal/log fault tests; no database or normal Local access."""
import importlib.util
import json
import os
from pathlib import Path
import signal
import socket
import sys
import tempfile
import threading

sys.dont_write_bytecode = True
module = Path(__file__).resolve().parents[2] / 'scripts/lib/bvm-disposable-db.py'
spec = importlib.util.spec_from_file_location('db_guard', module)
guard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)

FAKE = '''#!/usr/bin/env python3
import os,sys,time,socket,signal
from pathlib import Path
args=dict(a[2:].split('=',1) for a in sys.argv[1:] if a.startswith('--') and '=' in a)
mode=os.getenv('BVM_GUARD_FAULT','')
if '--initialize-insecure' in sys.argv:
    if mode=='init-failure':sys.exit(3)
    (Path(args['datadir'])/'fixture').write_text('disposable')
    sys.exit(0)
if mode=='startup-hang':time.sleep(20)
if mode=='server-exit':sys.exit(4)
sock=socket.socket(socket.AF_UNIX);sock.bind(args['socket']);sock.listen(5)
if mode in ('runaway','ignore-term'):signal.signal(signal.SIGTERM,signal.SIG_IGN)
if mode=='shutdown-log':
    def noisy_shutdown(n,f):
        Path(args['log-error']).write_bytes(b'x'*262144)
        sys.exit(0)
    signal.signal(signal.SIGTERM,noisy_shutdown)
while True:
    if mode=='runaway':
        with open(args['log-error'],'ab',buffering=0) as f:f.write(b'x'*65536)
    time.sleep(.002)
'''


def run_case(base, name, command, fault='', **limits):
    root = base / name
    root.mkdir()
    os.environ['BVM_GUARD_FAULT'] = fault
    instance = guard.Supervisor(root, base/'fake-server', base/(name+'.json'),
                                minimum=1, maximum=128*1024, timeout=3,
                                startup=.4, grace=.1, **limits)
    code = instance.run(command)
    result = instance.result
    assert result['no_process_remains'] and result['database_residue_absent'], result
    assert not list(root.iterdir()), result
    for log in base.glob(name+'-*.log'):
        assert log.stat().st_size <= guard.RETAIN_LOG
    return code, result


def main():
    checks = []
    with tempfile.TemporaryDirectory(prefix='bvm-db-guard-test-', dir='/private/tmp') as temp:
        base = Path(temp)
        fake = base/'fake-server'
        fake.write_text(FAKE)
        fake.chmod(0o700)
        success = [sys.executable, '-c', 'print("ok")']
        sleeper = [sys.executable, '-c', 'import time;time.sleep(20)']
        code, result = run_case(base,'success',success)
        assert code == 0 and not list(base.glob('success-*.log'))
        checks.append('success removes logs, datadir, socket and processes')
        for name,command,fault,error in [
            ('failure',[sys.executable,'-c','raise SystemExit(7)'],'','child_exit:7'),
            ('timeout',sleeper,'','runtime_limit'),
            ('runaway',sleeper,'runaway','log_size_limit'),
            ('init-failure',success,'init-failure','child_exit:3'),
            ('startup-hang',success,'startup-hang','startup_timeout'),
            ('server-exit',success,'server-exit','server_startup_exit'),
        ]:
            code,result=run_case(base,name,command,fault)
            assert code==1 and error in result['error'],result
            checks.append(name+' fails closed with bounded evidence and complete teardown')
        code,result=run_case(base,'stuck-shutdown',success,'ignore-term')
        assert code==0
        checks.append('stuck SIGTERM shutdown escalates and proves exit before deleting data')
        code,result=run_case(base,'shutdown-log',success,'shutdown-log')
        assert code==1 and result['error'].startswith('log_size_limit:'),result
        checks.append('log growth during teardown fails the run and retains only bounded evidence')
        code,result=run_case(base,'grandchild',[sys.executable,'-c',
            'import subprocess,sys;subprocess.Popen([sys.executable,"-c","import time;time.sleep(20)"])'])
        assert code==0
        checks.append('successful command cannot leave a child in its process group')
        timer=threading.Timer(.25,lambda:os.kill(os.getpid(),signal.SIGTERM))
        timer.start()
        try:
            code,result=run_case(base,'signal',sleeper)
            assert code==1 and result['error']=='signal_15',result
        finally:timer.join()
        checks.append('signal tears down child and database groups')
        root=base/'collision';root.mkdir()
        lock=root/'.staffing-db-guard.lock';lock.mkdir()
        canary=lock/'owner';canary.write_text('existing owner')
        instance=guard.Supervisor(root,fake,base/'collision.json',minimum=1)
        assert instance.run(success)==1 and canary.read_text()=='existing owner' and not instance.children
        checks.append('existing resource lock is never overwritten or adopted')
        root=base/'socket-collision';root.mkdir()
        with socket.socket(socket.AF_UNIX) as sock:
            sock.bind(str(root/'mysql.sock'))
            instance=guard.Supervisor(root,fake,base/'socket-collision.json',minimum=1)
            assert instance.run(success)==1 and (root/'mysql.sock').exists() and not instance.children
        checks.append('existing socket is never killed or removed')
        root=base/'low-disk';root.mkdir()
        instance=guard.Supervisor(root,fake,base/'low-disk.json',minimum=2**80)
        assert instance.run(success)==1 and instance.result['error']=='disk_space_limit' and not instance.children
        checks.append('low disk refuses startup before creating test resources')
        root=base/'disk-during-run';root.mkdir()
        instance=guard.Supervisor(root,fake,base/'disk-during-run.json',minimum=100,grace=.1)
        instance.free=lambda:999 if len(instance.children)<2 else 0
        assert instance.run(sleeper)==1 and instance.result['error']=='disk_space_limit'
        assert instance.result['no_process_remains'] and instance.result['database_residue_absent']
        checks.append('disk threshold during startup terminates and reclaims the owned server')
        root=base/'exit-proof-failure';root.mkdir()
        instance=guard.Supervisor(root,fake,base/'exit-proof-failure.json',minimum=1,grace=.1)
        real_stop=instance.stop
        def failed_proof(process):
            real_stop(process)
            raise guard.GuardFailure('injected exit proof failure')
        instance.stop=failed_proof
        assert instance.run(success)==1 and 'teardown_error' in instance.result
        assert instance.data.exists() and all(not instance.group_alive(p) for p in instance.children)
        checks.append('failed exit proof retains data and still attempts every owned process cleanup')
    print(json.dumps({'ok':True,'checks':checks,'count':len(checks)},indent=2))


if __name__=='__main__':main()
