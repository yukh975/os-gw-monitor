<?php
/**
 * os-gw-monitor plugin hook
 * Регистрирует сервисы и перехватывает перезапуск dpinger
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
 * Хук monitor — вызывается после dpinger_configure_do()
 * когда OPNsense перезапускает мониторинг шлюзов.
 * Восстанавливает наши HTTP-мониторы после того как dpinger
 * убил все /var/run/dpinger_*.pid
 */
function gw_monitor_configure()
{
    return [
        'monitor' => ['gw_monitor_configure_do:2'],
    ];
}

function gw_monitor_configure_do($verbose = false, $gwname_map = null)
{
    // Небольшая пауза чтобы dpinger успел запустить свои процессы
    sleep(1);

    // Перезапускаем только наши HTTP-мониторы
    $output = shell_exec('/usr/local/sbin/gwmonitor-service.php reconfigure');

    if ($verbose) {
        echo "gw_monitor: " . trim($output) . "\n";
    }
}
