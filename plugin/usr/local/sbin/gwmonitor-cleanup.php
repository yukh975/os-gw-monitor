#!/usr/local/bin/php
<?php
/**
 * gwmonitor-cleanup.php
 * Очищает статус и сокеты наших шлюзов при удалении плагина
 */

$xml = simplexml_load_file('/conf/config.xml');
$our_gateways = [];

function is_valid_gw_name(string $name): bool
{
    return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
}

if (isset($xml->OPNsense->GwMonitor)) {
    foreach ($xml->OPNsense->GwMonitor->monitors as $m) {
        $gw = trim((string)$m->gw_name);
        if (!empty($gw) && is_valid_gw_name($gw)) {
            $our_gateways[] = $gw;
        }
    }
}

if (empty($our_gateways)) {
    echo "No monitors found in config.xml\n";
    exit(0);
}

// Очистить gateways.status
$status_file = '/tmp/gateways.status';
if (file_exists($status_file)) {
    $fp = fopen($status_file, 'r+');
    if ($fp && flock($fp, LOCK_EX)) {
        $raw    = stream_get_contents($fp);
        $status = @unserialize($raw, ['allowed_classes' => false]);
        if (is_array($status)) {
            foreach ($our_gateways as $gw) {
                unset($status[$gw]);
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, serialize($status));
            echo "Cleared gateways.status\n";
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// Удалить сокеты и pid файлы
foreach ($our_gateways as $gw) {
    @unlink("/var/run/dpinger_{$gw}.sock");
    @unlink("/var/run/dpinger_{$gw}.pid");
    echo "Removed socket/pid for {$gw}\n";
}

// Удалить из config.xml если передан аргумент --purge
if (in_array('--purge', $argv ?? [])) {
    unset($xml->OPNsense->GwMonitor);
    $dom = dom_import_simplexml($xml)->ownerDocument;
    $dom->formatOutput = true;
    $dom->save('/conf/config.xml');
    echo "Removed GwMonitor from config.xml\n";
}
