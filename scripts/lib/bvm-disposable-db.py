#!/usr/bin/env python3
"""Test-only foreground database supervisor; never controls an existing server.

Run a command with a fresh MySQL 8.0 datadir and a private Unix socket. The
supervisor remains independent of the test command and owns every child group.
No datadir removal is permitted until all owned process groups have exited.
"""
import argparse
import json
import os
from pathlib import Path
import shutil
import signal
import socket
import subprocess
import sys
import time

MIN_FREE = 3 * 1024**3
MAX_LOG = 8 * 1024**2
RETAIN_LOG = 1024**2
ALLOWED = Path('/private/tmp/bvm-authority-integration-20260906/runtime')


class GuardFailure(RuntimeError):
    pass


class Supervisor:
    def __init__(self, root, server, receipt, minimum=MIN_FREE, maximum=MAX_LOG,
                 timeout=900, startup=60, grace=5):
        self.root, self.server, self.receipt = Path(root), str(server), Path(receipt)
        self.minimum, self.maximum = minimum, maximum
        self.timeout, self.startup, self.grace = timeout, startup, grace
        self.lock = self.root / '.staffing-db-guard.lock'
        self.data = self.lock / 'data'
        self.sock = self.root / 'mysql.sock'
        self.children, self.logs, self.peak = [], [], {}
        self.started = time.monotonic()
        self.interrupted = None
        self.result = {'ok': False, 'processes': [], 'limits': {
            'minimum_free_bytes': minimum, 'maximum_log_bytes': maximum,
            'retained_failure_log_bytes': RETAIN_LOG, 'timeout_seconds': timeout}}

    def free(self):
        return shutil.disk_usage(self.root).free

    def check(self):
        if self.interrupted:
            raise GuardFailure('signal_' + str(self.interrupted))
        if time.monotonic() - self.started > self.timeout:
            raise GuardFailure('runtime_limit')
        if self.free() < self.minimum:
            raise GuardFailure('disk_space_limit')
        for log in self.logs:
            n = log.stat().st_size if log.exists() else 0
            self.peak[log.name] = max(n, self.peak.get(log.name, 0))
            if n > self.maximum:
                raise GuardFailure('log_size_limit:' + log.name)

    def spawn(self, args, name, env=None):
        log = self.lock / (name + '.log')
        self.logs.append(log)
        with log.open('xb') as stream:
            process = subprocess.Popen(args, stdout=stream, stderr=stream,
                                       env=env, start_new_session=True)
        self.children.append(process)
        self.result['processes'].append({'name': name, 'pid': process.pid})
        return process

    @staticmethod
    def group_alive(process):
        process.poll()  # Reap an exited leader before checking its group.
        try:
            os.killpg(process.pid, 0)
            return True
        except ProcessLookupError:
            return False

    def stop(self, process):
        if self.group_alive(process):
            os.killpg(process.pid, signal.SIGTERM)
        deadline = time.monotonic() + self.grace
        while self.group_alive(process) and time.monotonic() < deadline:
            if any(log.exists() and log.stat().st_size > self.maximum for log in self.logs):
                break  # A runaway logger gets no additional graceful-shutdown allowance.
            time.sleep(.05)
        if self.group_alive(process):
            os.killpg(process.pid, signal.SIGKILL)
        deadline = time.monotonic() + 5
        while self.group_alive(process) and time.monotonic() < deadline:
            time.sleep(.05)
        if self.group_alive(process):
            raise GuardFailure('process_group_remains:' + str(process.pid))
        process.wait(timeout=1)

    def wait(self, process, deadline=None):
        while True:
            self.check()
            code = process.poll()
            if code is not None:
                if code:
                    raise GuardFailure('child_exit:' + str(code))
                return
            if deadline and time.monotonic() > deadline:
                raise GuardFailure('startup_timeout')
            time.sleep(.05)

    def run(self, command):
        owned = False
        handlers = {}
        try:
            self.result['free_before'] = self.free()
            self.check()
            # Existing resources are never adopted, killed or overwritten.
            if self.sock.exists() or self.sock.is_symlink():
                raise GuardFailure('socket_collision')
            self.lock.mkdir(mode=0o700)
            owned = True
            for sig in (signal.SIGINT, signal.SIGTERM, signal.SIGHUP):
                handlers[sig] = signal.signal(sig, lambda n, f: setattr(self, 'interrupted', n))
            self.data.mkdir()
            common = [self.server, '--no-defaults', '--datadir=' + str(self.data)]
            init = self.spawn(common + ['--initialize-insecure'], 'initialize')
            self.wait(init, time.monotonic() + self.startup)
            # A foreground init must not leave an orphan before server startup.
            if self.group_alive(init):
                raise GuardFailure('initialization_child_remains')
            error = self.lock / 'mysql-error.log'
            self.logs.append(error)
            db = self.spawn(common + ['--socket=' + str(self.sock),
                '--pid-file=' + str(self.lock / 'mysql.pid'), '--skip-networking',
                '--mysqlx=OFF', '--skip-log-bin', '--log-error=' + str(error)], 'server')
            deadline = time.monotonic() + self.startup
            while True:
                self.check()
                if db.poll() is not None:
                    raise GuardFailure('server_startup_exit')
                if self.sock.exists():
                    with socket.socket(socket.AF_UNIX) as probe:
                        probe.settimeout(.2)
                        try:
                            probe.connect(str(self.sock))
                            break
                        except OSError:
                            pass
                if time.monotonic() > deadline:
                    raise GuardFailure('startup_timeout')
                time.sleep(.05)
            env = dict(os.environ, BVM_DISPOSABLE_DB_SOCKET=str(self.sock),
                       BVM_DISPOSABLE_DB_GUARDED='1')
            job = self.spawn(command, 'test-command', env)
            while job.poll() is None:
                self.check()
                if db.poll() is not None:
                    raise GuardFailure('server_exited_during_test')
                time.sleep(.05)
            self.wait(job)
            self.result['ok'] = True
        except (GuardFailure, OSError, subprocess.SubprocessError) as error:
            self.result['error'] = str(error)
            self.result['ok'] = False
        finally:
            if owned:
                try:
                    cleanup_errors = []
                    for child in reversed(self.children):
                        try:
                            self.stop(child)
                        except (GuardFailure, OSError, subprocess.SubprocessError) as error:
                            cleanup_errors.append(str(error))
                    if cleanup_errors:
                        raise GuardFailure('; '.join(cleanup_errors))
                    self.result['no_process_remains'] = all(not self.group_alive(p) for p in self.children)
                    for log in self.logs:
                        if log.exists():
                            self.peak[log.name] = max(log.stat().st_size, self.peak.get(log.name, 0))
                            if self.peak[log.name] > self.maximum:
                                self.result.update(ok=False, error='log_size_limit:' + log.name)
                            if not self.result['ok']:
                                # Preserve bounded tail only after all writers stop.
                                with log.open('rb') as stream:
                                    stream.seek(max(0, log.stat().st_size - RETAIN_LOG))
                                    self.receipt.with_name(self.receipt.stem + '-' + log.name).write_bytes(stream.read(RETAIN_LOG))
                    # Only this invocation's exclusive lock/datadir is disposable.
                    shutil.rmtree(self.lock)
                    if self.sock.exists():
                        if not self.sock.is_socket():
                            raise GuardFailure('unexpected_socket_replacement')
                        self.sock.unlink()
                    self.result['database_residue_absent'] = not self.lock.exists() and not self.sock.exists()
                except (GuardFailure, OSError, subprocess.SubprocessError) as error:
                    self.result.update(ok=False, teardown_error=str(error))
                    # Keep the datadir if exit cannot be proven. Never repeat the
                    # incident's delete-data-while-mysqld-is-still-running sequence.
                for sig, handler in handlers.items():
                    signal.signal(sig, handler)
            self.result['peak_log_bytes'] = self.peak
            self.result['free_after'] = self.free()
            self.receipt.write_text(json.dumps(self.result, indent=2) + '\n')
        return 0 if self.result['ok'] else 1


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--server', required=True)
    parser.add_argument('--receipt', required=True)
    parser.add_argument('--timeout', type=int, default=900)
    parser.add_argument('command', nargs=argparse.REMAINDER)
    args = parser.parse_args()
    command = args.command[1:] if args.command[:1] == ['--'] else args.command
    receipt = Path(args.receipt)
    if receipt.exists() or receipt.is_symlink() or not receipt.parent.is_dir():
        parser.error('A new receipt path in an existing evidence directory is required')
    if not command or not ALLOWED.is_dir() or ALLOWED.resolve() != ALLOWED:
        parser.error('Explicit command and existing hard-bound disposable runtime required')
    expected = Path.home() / 'Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysqld'
    if Path(args.server) != expected or not expected.is_file() or args.timeout < 1 or args.timeout > 3600:
        parser.error('Only the verified MySQL 8.0.35 binary and a bounded timeout are allowed')
    return Supervisor(ALLOWED, expected, args.receipt, timeout=args.timeout).run(command)


if __name__ == '__main__':
    sys.exit(main())
