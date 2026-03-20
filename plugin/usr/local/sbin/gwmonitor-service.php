#!/usr/local/bin/php
<?php
/**
 * gwmonitor-service.php — manages gateway monitoring instances
 * Invoked via configd:
 *   gwmonitor reconfigure
 *   gwmonitor status
 *   gwmonitor start <uuid>
 *   gwmonitor stop <uuid>
 *   gwmonitor watchdog
 */

require_once('config.inc');
require_once('util.inc');

$action = $argv[1] ?? 'status';
$param  = $argv[2] ?? null;

$SOCKET_PY = '/usr/local/sbin/gw_monitor_probe.py';
$RUN_DIR   = '/var/run';
$LOG_DIR   = '/var/log';

function is_valid_gw_name(string $name): bool
{
    return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
}

function is_valid_uuid(string $uuid): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) === 1;
}

function is_valid_probe_host(string $host): bool
{
    if (empty($host)) return false;
    if (!preg_match('/^[a-zA-Z0-9._\[\]:-]+$/', $host)) return false;

    $blocked_names = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];
    if (in_array(strtolower($host), $blocked_names, true)) return false;

    $ip_str = trim($host, '[]');

    if (filter_var($ip_str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $addr = ip2long($ip_str);
        if ($addr === false) return false;
        if (($addr & 0xFF000000) === 0x7F000000) return false; // 127.0.0.0/8  loopback
        if (($addr & 0xFFFF0000) === 0xA9FE0000) return false; // 169.254.0.0/16 link-local
        if (($addr & 0xF0000000) === 0xE0000000) return false; // 224.0.0.0/4  multicast
        if (($addr & 0xF0000000) === 0xF0000000) return false; // 240.0.0.0/4  reserved
        if ($addr === 0x00000000) return false;                 // 0.0.0.0      unspecified
        if ($addr === (int)0xFFFFFFFF) return false;            // 255.255.255.255 broadcast
        return true;
    }

    if (filter_var($ip_str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $bin = inet_pton($ip_str);
        if ($bin === false) return false;
        $b0 = ord($bin[0]);
        $b1 = ord($bin[1]);

        if ($ip_str === '::' || $ip_str === '::1') return false;      // unspecified / loopback
        if ($b0 === 0xFF) return false;                                // ff00::/8  multicast
        if ($b0 === 0xFE && ($b1 & 0xC0) === 0x80) return false;      // fe80::/10 link-local
        if ($b0 === 0xFE && ($b1 & 0xC0) === 0xC0) return false;      // fec0::/10 site-local
        if (($b0 & 0xFE) === 0xFC) return false;                      // fc00::/7  unique local
        if (substr($bin, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            // IPv4-mapped ::ffff:x.x.x.x — check the embedded IPv4 part
            $ipv4 = long2ip(unpack('N', substr($bin, 12, 4))[1]);
            return is_valid_probe_host($ipv4);
        }
        return true;
    }

    // Hostname — resolve once and validate the resulting IP to prevent DNS rebinding
    $resolved = gethostbyname($host);
    if ($resolved === $host) return false; // resolution failed
    return is_valid_probe_host($resolved);
}

/**
 * Reads all instances from config.xml
 */
function get_monitors(): array
{
    $config = include_once('/conf/config.php') ?: [];
    $raw = simplexml_load_file('/conf/config.xml');
    if (!$raw) return [];

    $monitors = [];
    // Each <monitors uuid="..."> is a separate instance
    $nodes = $raw->xpath('//OPNsense/GwMonitor/monitors');
    if (!$nodes) return [];

    foreach ($nodes as $node) {
        $uuid = (string)$node->attributes()['uuid'] ?? null;
        if (!$uuid) continue;
        $gw_name   = (string)$node->gw_name;
        $probe_if  = (string)$node->probe_if;
        $probe_port = (int)$node->probe_port;

        $probe_host = (string)$node->probe_host;

        $probe_count    = (int)$node->probe_count    ?: 5;
        $probe_interval = (int)$node->probe_interval ?: 25;
        $probe_timeout  = (int)$node->probe_timeout  ?: 5;

        if (!is_valid_uuid($uuid) || !is_valid_gw_name($gw_name)) continue;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $probe_if)) continue;
        if ($probe_port < 1 || $probe_port > 65535) continue;
        if (!is_valid_probe_host($probe_host)) continue;
        if ($probe_count < 1    || $probe_count > 20)    continue;
        if ($probe_interval < 5 || $probe_interval > 300) continue;
        if ($probe_timeout < 1  || $probe_timeout > 30)  continue;

        $monitors[$uuid] = [
            'uuid'           => $uuid,
            'enabled'        => (string)$node->enabled === '1',
            'gw_name'        => $gw_name,
            'probe_if'       => $probe_if,
            'probe_host'     => $probe_host,
            'probe_port'     => $probe_port,
            'probe_count'    => $probe_count,
            'probe_interval' => $probe_interval,
            'probe_timeout'  => $probe_timeout,
            'description'    => (string)$node->description,
        ];
    }
    return $monitors;
}

/**
 * Checks whether an instance is alive
 */
function is_running(string $gw_name): bool
{
    global $RUN_DIR;
    $pidfile  = "{$RUN_DIR}/dpinger_{$gw_name}.pid";
    $sockfile = "{$RUN_DIR}/dpinger_{$gw_name}.sock";
    if (!file_exists($pidfile) || !file_exists($sockfile)) return false;
    $pid = (int)trim(file_get_contents($pidfile));
    return $pid > 0 && posix_kill($pid, 0);
}

/**
 * Starts an instance
 */
function start_instance(array $m): void
{
    global $RUN_DIR, $LOG_DIR, $SOCKET_PY;

    $gw   = escapeshellarg($m['gw_name']);
    $sock = "{$RUN_DIR}/dpinger_{$m['gw_name']}.sock";
    $pid  = "{$RUN_DIR}/dpinger_{$m['gw_name']}.pid";

    // Kill old process if exists
    stop_instance($m['gw_name']);

    $cmd = sprintf(
        '/usr/local/bin/python3 %s %s %s %s %s %s %d %d %d >> %s/gwmonitor_%s.log 2>&1 &',
        escapeshellarg($SOCKET_PY),
        escapeshellarg($sock),
        escapeshellarg($m['gw_name']),
        escapeshellarg($m['probe_host']),
        escapeshellarg($m['probe_port']),
        escapeshellarg($m['probe_if']),
        $m['probe_count'],
        $m['probe_interval'],
        $m['probe_timeout'],
        $LOG_DIR,
        preg_replace('/[^a-z0-9_-]/i', '_', $m['gw_name'])
    );

    exec($cmd, $out, $rc);

    // Wait for the socket to appear (up to 10 seconds)
    // Using lstat() for atomic check without following symlinks
    for ($i = 0; $i < 20; $i++) {
        usleep(500000);
        $stat = @lstat($sock);
        if ($stat !== false && ($stat['mode'] & 0170000) === 0140000) break; // S_IFSOCK
    }
    // PID is written atomically by the Python process itself at startup
}

/**
 * Stops an instance
 */
function stop_instance(string $gw_name): void
{
    global $RUN_DIR;
    $pidfile  = "{$RUN_DIR}/dpinger_{$gw_name}.pid";
    $sockfile = "{$RUN_DIR}/dpinger_{$gw_name}.sock";

    if (file_exists($pidfile) && !is_link($pidfile)) {
        $pid = (int)trim(file_get_contents($pidfile));
        if ($pid > 0) {
            posix_kill($pid, SIGTERM);
            usleep(500000);
            // Force kill by exact PID if still alive
            if (posix_kill($pid, 0)) posix_kill($pid, SIGKILL);
        }
    }

    if (!is_link($pidfile))  @unlink($pidfile);
    if (!is_link($sockfile)) @unlink($sockfile);
}

/**
 * Reads current data from the dpinger socket
 */
function read_socket(string $gw_name): ?array
{
    global $RUN_DIR;
    $sockfile = "{$RUN_DIR}/dpinger_{$gw_name}.sock";

    // Atomic check via lstat (does not follow symlinks)
    $stat = @lstat($sockfile);
    if ($stat === false || ($stat['mode'] & 0170000) !== 0140000) return null;
    if (is_link($sockfile)) return null;

    $fp = @stream_socket_client("unix://{$sockfile}", $errno, $errstr, 1);
    if (!$fp) return null;

    $data  = '';
    $lines = 0;
    while (!feof($fp) && $lines < 10) {
        $data .= fgets($fp, 256);
        $lines++;
    }
    fclose($fp);

    $parts = explode(' ', trim($data));
    if (count($parts) < 4) return null;

    return [
        'rtt'  => round((int)$parts[1] / 1000, 1),
        'rttd' => round((int)$parts[2] / 1000, 1),
        'loss' => (int)$parts[3],
    ];
}

// ── Command handlers ──────────────────────────────────────────────────

switch ($action) {

    case 'reconfigure':
        $lock_file = '/var/run/gwmonitor-reconfigure.lock';
        $lock_fp   = fopen($lock_file, 'c');
        if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB)) {
            echo "ERROR: reconfigure already running\n";
            exit(1);
        }
        $monitors = get_monitors();
        // Stop only our instances (python3 gw_monitor_probe.py)
        // Do NOT touch standard dpinger processes
        foreach ($monitors as $m) {
            if (!empty($m['gw_name'])) {
                stop_instance($m['gw_name']);
            }
        }
        // Start only enabled instances
        $started = 0;
        foreach ($monitors as $m) {
            if ($m['enabled'] && !empty($m['gw_name']) && !empty($m['probe_if'])) {
                start_instance($m);
                $started++;
            }
        }
        flock($lock_fp, LOCK_UN);
        fclose($lock_fp);
        echo "OK: reconfigured, {$started} monitor(s) started\n";
        break;

    case 'status':
        $monitors = get_monitors();
        $result   = ['monitors' => []];
        foreach ($monitors as $uuid => $m) {
            $running = is_running($m['gw_name']);
            $entry   = [
                'uuid'        => $uuid,
                'gw_name'     => $m['gw_name'],
                'enabled'     => $m['enabled'],
                'running'     => $running,
                'rtt'         => '—',
                'rttd'        => '—',
                'loss'        => '—',
            ];
            if ($running) {
                $sock = read_socket($m['gw_name']);
                if ($sock) {
                    $entry['rtt']  = $sock['rtt'];
                    $entry['rttd'] = $sock['rttd'];
                    $entry['loss'] = $sock['loss'];
                }
            }
            $result['monitors'][$uuid] = $entry;
        }
        echo json_encode($result) . "\n";
        break;

    case 'start':
        if (!$param) { echo "ERROR: uuid required\n"; exit(1); }
        if (!is_valid_uuid($param)) { echo "ERROR: invalid uuid\n"; exit(1); }
        $monitors = get_monitors();
        if (!isset($monitors[$param])) { echo "ERROR: monitor not found\n"; exit(1); }
        start_instance($monitors[$param]);
        echo "OK\n";
        break;

    case 'stop':
        if (!$param) { echo "ERROR: uuid required\n"; exit(1); }
        if (!is_valid_uuid($param)) { echo "ERROR: invalid uuid\n"; exit(1); }
        $monitors = get_monitors();
        if (!isset($monitors[$param])) { echo "ERROR: monitor not found\n"; exit(1); }
        stop_instance($monitors[$param]['gw_name']);
        echo "OK\n";
        break;

    case 'watchdog':
        $monitors = get_monitors();
        $restarted = 0;
        foreach ($monitors as $m) {
            if (!$m['enabled']) continue;
            if (!is_running($m['gw_name'])) {
                syslog(LOG_WARNING, "gw_monitor: {$m['gw_name']} not running, restarting");
                start_instance($m);
                $restarted++;
            }
        }
        echo "OK: watchdog checked, {$restarted} restarted\n";
        break;

    default:
        echo "ERROR: unknown action '{$action}'\n";
        exit(1);
}
