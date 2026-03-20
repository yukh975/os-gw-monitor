<?php
/**
 * os-gw-monitor plugin hook
 * Registers services and intercepts dpinger restarts
 */

function gw_monitor_services()
{
    $services = [];
    $services[] = [
        'description' => 'Gateway HTTP Monitor',
        'configd'     => [
            'restart' => ['gwmonitor reconfigure'],
            'start'   => ['gwmonitor reconfigure'],
            'stop'    => ['gwmonitor reconfigure'],
        ],
        'name' => 'gwmonitor',
    ];
    return $services;
}

/**
 * Monitor hook — called after dpinger_configure_do()
 * when OPNsense restarts gateway monitoring.
 * Restores our HTTP monitors after dpinger
 * has killed all /var/run/dpinger_*.pid files
 */
function gw_monitor_configure()
{
    return [
        'monitor' => ['gw_monitor_configure_do:2'],
    ];
}

function gw_monitor_configure_do($verbose = false, $gwname_map = null)
{
    // Short pause to allow dpinger to start its processes
    sleep(1);

    // Restart only our HTTP monitors
    $output = shell_exec('/usr/local/sbin/gwmonitor-service.php reconfigure');

    if ($verbose) {
        echo "gw_monitor: " . trim($output) . "\n";
    }
}
