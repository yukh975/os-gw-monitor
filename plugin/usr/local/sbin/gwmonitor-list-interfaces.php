#!/usr/local/bin/php
<?php
/**
 * Returns a list of physical interfaces in JsonKeyValueStoreField format:
 * {"if1": "if1 (description)", ...}
 */

$output = [];
exec('/sbin/ifconfig -l', $lines, $rc);
if ($rc !== 0 || empty($lines)) {
    echo json_encode([]);
    exit(0);
}

// Skip system interfaces
$skip = ['lo0', 'enc0', 'pflog0', 'pfsync0'];

$ifaces = explode(' ', trim($lines[0]));

// Try to get descriptions from config.xml
$descriptions = [];
$config = simplexml_load_file('/conf/config.xml');
if ($config) {
    foreach ($config->interfaces->children() as $name => $iface) {
        $dev = (string)$iface->if;
        $descr = !empty((string)$iface->descr) ? htmlspecialchars((string)$iface->descr, ENT_QUOTES, 'UTF-8') : strtoupper($name);
        if ($dev) {
            $descriptions[$dev] = $descr;
        }
    }
}

foreach ($ifaces as $if) {
    if (in_array($if, $skip)) continue;
    if (empty(trim($if))) continue;
    if (!preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_-]*$/', $if)) continue;
    $label = isset($descriptions[$if])
        ? "{$if} ({$descriptions[$if]})"
        : $if;
    $output[$if] = $label;
}

echo json_encode($output) . "\n";
