#!/usr/local/bin/python3
import socket, os, sys, threading, time, subprocess, logging, traceback, re

def _validate_args():
    if len(sys.argv) < 9:
        sys.stderr.write('Usage: gw_monitor_probe.py sock_path gw_name host port iface count interval timeout\n')
        sys.exit(1)

    gw = sys.argv[2]
    if not re.match(r'^[a-zA-Z0-9_-]+$', gw):
        sys.stderr.write('Invalid gw_name: {}\n'.format(gw))
        sys.exit(1)

    host = sys.argv[3]
    if not re.match(r'^[a-zA-Z0-9._\[\]:-]+$', host):
        sys.stderr.write('Invalid probe_host: {}\n'.format(host))
        sys.exit(1)

    port = int(sys.argv[4])
    if not (1 <= port <= 65535):
        sys.stderr.write('Invalid probe_port: {}\n'.format(port))
        sys.exit(1)

    iface = sys.argv[5]
    if not re.match(r'^[a-zA-Z0-9_]+$', iface):
        sys.stderr.write('Invalid probe_if: {}\n'.format(iface))
        sys.exit(1)

    return gw

gw_name_raw = _validate_args()

logging.basicConfig(
    filename='/var/log/gwmonitor_{}.log'.format(gw_name_raw),
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

state = {'lat': 0, 'std': 0, 'loss': 100}
state_lock = threading.Lock()

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
                 'http://{}:{}/'.format(probe_host, int(probe_port))],
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
        with state_lock:
            msg = '{} {} {} {}\n'.format(gw_name, state['lat'], state['std'], state['loss'])
        conn.sendall(msg.encode())
    except Exception:
        log_exception('handle failed')
    finally:
        conn.close()

try:
    do_probe()
    threading.Thread(target=probe_loop, daemon=True).start()

    try:
        os.unlink(sock_path)
    except OSError:
        pass

    srv = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    srv.bind(sock_path)
    os.chmod(sock_path, 0o660)
    srv.listen(5)

    while True:
        try:
            conn, _ = srv.accept()
            threading.Thread(target=handle, args=(conn,), daemon=True).start()
        except Exception:
            log_exception('accept failed')
            time.sleep(0.1)
except Exception:
    log_exception('fatal error')
    sys.exit(1)
