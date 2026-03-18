#!/bin/sh
# =============================================================
# os-gw-monitor install.sh
# Использование:
#   sh install.sh           — установка
#   sh install.sh uninstall — удаление
# =============================================================

PLUGIN_DIR="$(cd "$(dirname "$0")/plugin" && pwd)"
OPNSENSE_MVC="/usr/local/opnsense/mvc/app"

do_install() {
    echo "=== Installing os-gw-monitor ==="

    # Python-демон зондирования
    install -m 0755 "${PLUGIN_DIR}/usr/local/sbin/gw_monitor_probe.py" \
        "/usr/local/sbin/gw_monitor_probe.py"

    # PHP скрипт списка интерфейсов
    install -m 0755 "${PLUGIN_DIR}/usr/local/sbin/gwmonitor-list-interfaces.php" \
        "/usr/local/sbin/gwmonitor-list-interfaces.php"

    # PHP backend
    install -m 0755 "${PLUGIN_DIR}/usr/local/sbin/gwmonitor-list-interfaces.php" \
        "/usr/local/sbin/gwmonitor-list-interfaces.php"
    install -m 0755 "${PLUGIN_DIR}/usr/local/sbin/gwmonitor-service.php" \
        "/usr/local/sbin/gwmonitor-service.php"

    # MVC модель
    mkdir -p "${OPNSENSE_MVC}/models/OPNsense/GwMonitor/Menu"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/models/OPNsense/GwMonitor/Monitor.xml" \
        "${OPNSENSE_MVC}/models/OPNsense/GwMonitor/Monitor.xml"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/models/OPNsense/GwMonitor/Monitor.php" \
        "${OPNSENSE_MVC}/models/OPNsense/GwMonitor/Monitor.php"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/models/OPNsense/GwMonitor/Menu/Menu.xml" \
        "${OPNSENSE_MVC}/models/OPNsense/GwMonitor/Menu/Menu.xml"

    # MVC форма диалога
    mkdir -p "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/forms"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/controllers/OPNsense/GwMonitor/forms/dialogMonitor.xml" \
        "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/forms/dialogMonitor.xml"

    # MVC контроллеры
    mkdir -p "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/Api"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/controllers/OPNsense/GwMonitor/IndexController.php" \
        "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/IndexController.php"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/controllers/OPNsense/GwMonitor/Api/MonitorController.php" \
        "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/Api/MonitorController.php"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/controllers/OPNsense/GwMonitor/Api/ServiceController.php" \
        "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/Api/ServiceController.php"

    # MVC view
    mkdir -p "${OPNSENSE_MVC}/views/OPNsense/GwMonitor"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/views/OPNsense/GwMonitor/index.volt" \
        "${OPNSENSE_MVC}/views/OPNsense/GwMonitor/index.volt"

    # Plugin hook
    install -m 0644 "${PLUGIN_DIR}/etc/inc/plugins.inc.d/gw_monitor.inc" \
        "/usr/local/etc/inc/plugins.inc.d/gw_monitor.inc"

    # Configd actions
    install -m 0644 "${PLUGIN_DIR}/service/conf/actions.d/actions_gwmonitor.conf" \
        "/usr/local/opnsense/service/conf/actions.d/actions_gwmonitor.conf"

    # Перезапуск configd и очистка кешей
    echo "  Restarting configd..."
    service configd restart
    sleep 1

    echo "  Clearing caches..."
    rm -f /tmp/opnsense_menu_cache.xml
    rm -f /tmp/opnsense_acl_cache.json 2>/dev/null
    rm -f /var/run/booting 2>/dev/null

    echo "  Starting monitors..."
    sleep 1
    # Перезапускаем весь мониторинг шлюзов через pluginctl
    # Это запустит dpinger для штатных шлюзов И вызовет наш хук gw_monitor_configure_do
    /usr/local/sbin/pluginctl -c monitor
    sleep 2
    /usr/local/sbin/gwmonitor-service.php reconfigure

    echo ""
    echo "=== Installation complete ==="
    echo ""
    echo "Refresh browser (Ctrl+F5) and go to:"
    echo "  System → Gateways → Monitoring"
    echo ""
    echo "Add monitors via GUI, click Apply."
    echo "Add watchdog in Cron:"
    echo "  System → Settings → Cron → Command: GW Monitor Watchdog, all fields: *"
}

do_uninstall() {
    echo "=== Uninstalling os-gw-monitor ==="

    # Остановить все инстансы
    pkill -f "gw_monitor_probe.py" 2>/dev/null || true
    rm -f /var/run/dpinger_*.pid /var/run/dpinger_*.sock

    # Удалить файлы
    rm -f /usr/local/sbin/gw_monitor_probe.py
    rm -f /usr/local/sbin/gwmonitor-service.php
    rm -f /usr/local/sbin/gwmonitor-list-interfaces.php
    rm -f /usr/local/sbin/gwmonitor-list-interfaces.php
    rm -rf "${OPNSENSE_MVC}/models/OPNsense/GwMonitor"
    rm -rf "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor"
    rm -rf "${OPNSENSE_MVC}/views/OPNsense/GwMonitor"
    rm -f /usr/local/etc/inc/plugins.inc.d/gw_monitor.inc
    rm -f /usr/local/opnsense/service/conf/actions.d/actions_gwmonitor.conf

    # Кеши и логи
    rm -f /tmp/opnsense_menu_cache.xml
    rm -f /var/log/gwmonitor_*.log
    rm -f /var/log/tun2socks_socket.log

    echo "  Clearing caches..."
    rm -f /tmp/opnsense_menu_cache.xml
    rm -f /tmp/opnsense_acl_cache.json 2>/dev/null
    rm -f /var/run/booting 2>/dev/null

    echo "  Clearing caches..."
    rm -f /tmp/opnsense_menu_cache.xml
    rm -f /tmp/opnsense_acl_cache.json 2>/dev/null
    echo "  Restarting configd..."
    service configd restart

    echo ""
    echo "=== Uninstall complete ==="
    echo "Refresh browser (Ctrl+F5)"
}

case "$1" in
    uninstall) do_uninstall ;;
    *)         do_install   ;;
esac
