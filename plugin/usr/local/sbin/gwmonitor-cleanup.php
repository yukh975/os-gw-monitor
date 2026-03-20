#!/usr/local/bin/php
<?php
/**
 * gwmonitor-cleanup.php
 * Clears the status and sockets of our gateways when the plugin is removed
 */

$xml = @simplexml_load_file('/conf/config.xml');
$our_gateways = [];
if ($xml === false) {
    echo "WARNING: failed to parse config.xml, no monitors to clean up\n";
    exit(0);
}

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

// Clear gateways.status
$status_file = '/tmp/gateways.status';
if (file_exists($status_file)) {
    $fp = fopen($status_file, 'r+');
    if ($fp && flock($fp, LOCK_EX)) {
        // gateways.status is written by OPNsense core in PHP serialize format;
        // ['allowed_classes' => false] prevents Object Injection attacks.
        // Size guard prevents memory exhaustion from a crafted file.
        $raw = stream_get_contents($fp, 1048576); // 1 MB cap
        if (strlen($raw) >= 1048576) {
            echo "gateways.status too large, skipping\n";
        } else {
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
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// Remove sockets and pid files (verify they are not symlinks)
foreach ($our_gateways as $gw) {
    $sock_path = "/var/run/dpinger_{$gw}.sock";
    $pid_path  = "/var/run/dpinger_{$gw}.pid";
    if (file_exists($sock_path) && !is_link($sock_path)) @unlink($sock_path);
    if (file_exists($pid_path)  && !is_link($pid_path))  @unlink($pid_path);
    echo "Removed socket/pid for {$gw}\n";
}

// Remove from config.xml if the --purge argument is passed
if (in_array('--purge', $argv ?? [])) {
    unset($xml->OPNsense->GwMonitor);
    $dom = dom_import_simplexml($xml)->ownerDocument;
    $dom->formatOutput = true;
    $dom->save('/conf/config.xml');
    echo "Removed GwMonitor from config.xml\n";
}
