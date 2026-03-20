#!/usr/local/bin/python3
import socket, os, sys, threading, time, subprocess, logging, traceback, re, ipaddress

_BLOCKED_HOSTNAMES = {'localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'}

def _is_safe_host(host):
    if host.lower() in _BLOCKED_HOSTNAMES:
        return False
    try:
        addr = ipaddress.ip_address(host.strip('[]'))
        if (addr.is_loopback or addr.is_link_local or addr.is_unspecified or
                addr.is_reserved or addr.is_multicast):
            return False
        # Block IPv4-mapped addresses (e.g. ::ffff:127.0.0.1)
        if isinstance(addr, ipaddress.IPv6Address) and addr.ipv4_mapped is not None:
            mapped = addr.ipv4_mapped
            if (mapped.is_loopback or mapped.is_link_local or
                    mapped.is_unspecified or mapped.is_reserved or mapped.is_multicast):
                return False
    except ValueError:
        pass  # hostname — allow
    return True

def _validate_args():
    if len(sys.argv) < 9:
        sys.stderr.write('Usage: gw_monitor_probe.py sock_path gw_name host port iface count interval timeout\n')
        sys.exit(1)

    gw = sys.argv[2]
    if not re.match(r'^[a-zA-Z0-9_-]+$', gw):
        sys.stderr.write('Invalid gw_name: {}\n'.format(gw))
        sys.exit(1)

    host = sys.argv[3]
    if not re.match(r'^[a-zA-Z0-9._\[\]:-]+$', host) or not _is_safe_host(host):
        sys.stderr.write('Invalid or blocked probe_host: {}\n'.format(host))
        sys.exit(1)

    try:
        port = int(sys.argv[4])
    except ValueError:
        sys.stderr.write('Invalid probe_port: {}\n'.format(sys.argv[4]))
        sys.exit(1)
    if not (1 <= port <= 65535):
        sys.stderr.write('Invalid probe_port: {}\n'.format(port))
        sys.exit(1)

    iface = sys.argv[5]
    if not re.match(r'^[a-zA-Z0-9_]+$', iface):
        sys.stderr.write('Invalid probe_if: {}\n'.format(iface))
        sys.exit(1)

    try:
        count    = int(sys.argv[6])
        interval = int(sys.argv[7])
        timeout  = int(sys.argv[8])
    except ValueError as e:
        sys.stderr.write('Invalid probe parameter: {}\n'.format(e))
        sys.exit(1)
    if not (1 <= count <= 20):
        sys.stderr.write('Invalid probe_count: {}\n'.format(count))
        sys.exit(1)
    if not (5 <= interval <= 300):
        sys.stderr.write('Invalid probe_interval: {}\n'.format(interval))
        sys.exit(1)
    if not (1 <= timeout <= 30):
        sys.stderr.write('Invalid probe_timeout: {}\n'.format(timeout))
        sys.exit(1)

    return gw

gw_name_raw = _validate_args()

# Refuse to write logs if the target path is a symlink (symlink attack protection)
_log_path = '/var/log/gwmonitor_{}.log'.format(gw_name_raw)
if os.path.islink(_log_path):
    sys.stderr.write('Log file is a symlink, refusing to start: {}\n'.format(_log_path))
    sys.exit(1)

logging.basicConfig(
    filename=_log_path,
    level=logging.WARNING,
    format='%(asctime)s %(levelname)s %(message)s'
)

def log_exception(msg):
    logging.error('{}: {}'.format(msg, traceback.format_exc()))

sock_path  = sys.argv[1]
gw_name    = sys.argv[2]
probe_host = sys.argv[3]
probe_port = int(sys.argv[4])
probe_if   = sys.argv[5]
count      = int(sys.argv[6])
interval   = int(sys.argv[7])
timeout    = int(sys.argv[8])

# Write own PID atomically so PHP doesn't need to find it via pgrep/ps
_pid_path = '/var/run/dpinger_{}.pid'.format(gw_name)
try:
    with open(_pid_path, 'w') as _f:
        _f.write(str(os.getpid()))
    os.chmod(_pid_path, 0o600)
except Exception as _e:
    sys.stderr.write('Failed to write PID file: {}\n'.format(_e))
    sys.exit(1)

state = {'lat': 0, 'std': 0, 'loss': 100}
state_lock = threading.Lock()

_MAX_CONN = 10
_conn_sem = threading.Semaphore(_MAX_CONN)

_CONN_TIMEOUT = 5  # seconds — prevent hung connections from holding semaphore slots

def do_probe():
    samples = []
    lost = 0
    for _ in range(count):
        try:
            r = subprocess.run(
                ['/usr/local/bin/curl',
                 '--silent', '--fail',
                 '--interface', probe_if,
                 '--no-keepalive',
                 '--connect-timeout', str(timeout),
                 '--max-time', str(timeout),
                 '--output', '/dev/null',
                 '-w', '%{time_starttransfer}',
                 'http://{}:{}/'.format(
                     probe_host if probe_host.startswith('[') else
                     ('[' + probe_host + ']' if ':' in probe_host else probe_host),
                     int(probe_port))],
                capture_output=True, text=True, timeout=timeout + 2
            )
            val = float(r.stdout.strip()) if r.stdout.strip() else 0.0
            if r.returncode == 0 and val > 0.0001:
                samples.append(val)
            else:
                lost += 1
        except Exception:
            log_exception('curl failed')
            lost += 1

    with state_lock:
        if not samples:
            state['lat']  = 0
            state['std']  = 0
            state['loss'] = 100
        else:
            avg = sum(samples) / len(samples)
            std = sum(abs(x - avg) for x in samples) / len(samples)
            state['lat']  = int(avg * 1_000_000)
            state['std']  = int(std * 1_000_000)
            state['loss'] = int(round(lost * 100 / count))

def probe_loop():
    while True:
        try:
            do_probe()
        except Exception:
            log_exception('probe_loop failed')
        time.sleep(interval)

def handle(conn):
    try:
        conn.settimeout(_CONN_TIMEOUT)
        with state_lock:
            msg = '{} {} {} {}\n'.format(gw_name, state['lat'], state['std'], state['loss'])
        conn.sendall(msg.encode())
    except Exception:
        log_exception('handle failed')
    finally:
        conn.close()
        _conn_sem.release()

try:
    do_probe()
    threading.Thread(target=probe_loop, daemon=True).start()

    try:
        os.unlink(sock_path)
    except OSError:
        pass

    srv = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    srv.bind(sock_path)
    os.chmod(sock_path, 0o600)
    srv.listen(5)

    while True:
        try:
            conn, _ = srv.accept()
            if _conn_sem.acquire(blocking=False):
                threading.Thread(target=handle, args=(conn,), daemon=True).start()
            else:
                conn.close()  # connection limit reached — reject immediately
        except Exception:
            log_exception('accept failed')
            time.sleep(0.1)
except Exception:
    log_exception('fatal error')
    sys.exit(1)
