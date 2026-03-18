#!/usr/local/bin/php
<?php
/**
 * gwmonitor-service.php — управление инстансами мониторинга шлюзов
 * Вызывается через configd:
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

/**
 * Читает все инстансы из config.xml
 */
function get_monitors(): array
{
    $config = include_once('/conf/config.php') ?: [];
    $raw = simplexml_load_file('/conf/config.xml');
    if (!$raw) return [];

    $monitors = [];
    // Каждый <monitors uuid="..."> — отдельный инстанс
    $nodes = $raw->xpath('//OPNsense/GwMonitor/monitors');
    if (!$nodes) return [];

    foreach ($nodes as $node) {
        $uuid = (string)$node->attributes()['uuid'] ?? null;
        if (!$uuid) continue;
        $monitors[$uuid] = [
            'uuid'           => $uuid,
            'enabled'        => (string)$node->enabled === '1',
            'gw_name'        => (string)$node->gw_name,
            'probe_if'       => (string)$node->probe_if,
            'probe_host'     => (string)$node->probe_host,
            'probe_port'     => (string)$node->probe_port,
            'probe_count'    => (int)$node->probe_count    ?: 5,
            'probe_interval' => (int)$node->probe_interval ?: 25,
            'probe_timeout'  => (int)$node->probe_timeout  ?: 5,
            'description'    => (string)$node->description,
        ];
    }
    return $monitors;
}

/**
 * Проверяет жив ли инстанс
 */
function is_running(string $gw_name): bool
{
    global $RUN_DIR;
    $pidfile  = "{$RUN_DIR}/dpinger_{$gw_name}.pid";
    $sockfile = "{$RUN_DIR}/dpinger_{$gw_name}.sock";
    if (!file_exists($pidfile) || !file_exists($sockfile)) return false;
    $pid = (int)trim(file_get_contents($pidfile));
    return $pid > 0 && file_exists("/proc/$pid") || (shell_exec("kill -0 $pid; echo $?") == "0
");
}

/**
 * Запускает инстанс
 */
function start_instance(array $m): void
{
    global $RUN_DIR, $LOG_DIR, $SOCKET_PY;

    $gw   = escapeshellarg($m['gw_name']);
    $sock = "{$RUN_DIR}/dpinger_{$m['gw_name']}.sock";
    $pid  = "{$RUN_DIR}/dpinger_{$m['gw_name']}.pid";

    // Убиваем старый процесс если есть
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

    // Ждём появления сокета (до 10 секунд)
    for ($i = 0; $i < 20; $i++) {
        usleep(500000);
        if (file_exists($sock) && filetype($sock) === 'socket') break;
    }

    // Ищем PID python-процесса
    exec("pgrep -f " . escapeshellarg("gw_monitor_probe.py {$sock}"), $pids);
    if (!empty($pids)) {
        file_put_contents($pid, trim($pids[0]));
    }
}

/**
 * Останавливает инстанс
 */
function stop_instance(string $gw_name): void
{
    global $RUN_DIR;
    $pidfile  = "{$RUN_DIR}/dpinger_{$gw_name}.pid";
    $sockfile = "{$RUN_DIR}/dpinger_{$gw_name}.sock";

    if (file_exists($pidfile)) {
        $pid = (int)trim(file_get_contents($pidfile));
        if ($pid > 0) exec("kill " . (int)$pid . "");
    }

    // Небольшая пауза и принудительное завершение
    usleep(500000);
    exec("pkill -f " . escapeshellarg("gw_monitor_probe.py {$sockfile}") . "");

    @unlink($pidfile);
    @unlink($sockfile);
}

/**
 * Читает текущие данные из dpinger-сокета
 */
function read_socket(string $gw_name): ?array
{
    global $RUN_DIR;
    $sockfile = "{$RUN_DIR}/dpinger_{$gw_name}.sock";
    if (!file_exists($sockfile)) return null;

    $fp = @stream_socket_client("unix://{$sockfile}", $errno, $errstr, 1);
    if (!$fp) return null;

    $data = '';
    while (!feof($fp)) $data .= fgets($fp, 1024);
    fclose($fp);

    $parts = explode(' ', trim($data));
    if (count($parts) < 4) return null;

    return [
        'rtt'  => round((int)$parts[1] / 1000, 1),
        'rttd' => round((int)$parts[2] / 1000, 1),
        'loss' => (int)$parts[3],
    ];
}

// ── Обработчики команд ────────────────────────────────────────────────

switch ($action) {

    case 'reconfigure':
        $monitors = get_monitors();
        // Останавливаем только наши инстансы (python3 gw_monitor_probe.py)
        // НЕ трогаем штатные dpinger процессы
        foreach ($monitors as $m) {
            if (!empty($m['gw_name'])) {
                stop_instance($m['gw_name']);
            }
        }
        // Запускаем только включённые
        $started = 0;
        foreach ($monitors as $m) {
            if ($m['enabled'] && !empty($m['gw_name']) && !empty($m['probe_if'])) {
                start_instance($m);
                $started++;
            }
        }
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
        $monitors = get_monitors();
        if (!isset($monitors[$param])) { echo "ERROR: monitor not found\n"; exit(1); }
        start_instance($monitors[$param]);
        echo "OK\n";
        break;

    case 'stop':
        if (!$param) { echo "ERROR: uuid required\n"; exit(1); }
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
