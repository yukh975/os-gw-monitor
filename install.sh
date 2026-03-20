#!/bin/sh
# =============================================================
# os-gw-monitor install.sh
# Usage:
#   sh install.sh           — install
#   sh install.sh uninstall — uninstall
# =============================================================

PLUGIN_DIR="$(cd "$(dirname "$0")/plugin" && pwd)"
OPNSENSE_MVC="/usr/local/opnsense/mvc/app"

PLUGIN_VERSION="1.0.9"
VERSION_FILE="/var/db/gwmonitor-version"

do_install() {
    # Check installed version
    if [ -f "$VERSION_FILE" ]; then
        INSTALLED_VERSION="$(cat $VERSION_FILE | tr -d '[:space:]')"
        if [ "$INSTALLED_VERSION" = "$PLUGIN_VERSION" ]; then
            echo "os-gw-monitor v${PLUGIN_VERSION} already installed, skipping."
            exit 0
        fi
        echo "Upgrading os-gw-monitor from v${INSTALLED_VERSION} to v${PLUGIN_VERSION}..."
        # Remove old version preserving settings
        do_uninstall_silent
    fi
    echo "=== Installing os-gw-monitor v${PLUGIN_VERSION} ==="

    # Scripts in /usr/local/sbin
    install -m 0755 "${PLUGIN_DIR}/usr/local/sbin/gw_monitor_probe.py" \
        "/usr/local/sbin/gw_monitor_probe.py"
    install -m 0755 "${PLUGIN_DIR}/usr/local/sbin/gwmonitor-service.php" \
        "/usr/local/sbin/gwmonitor-service.php"
    install -m 0755 "${PLUGIN_DIR}/usr/local/sbin/gwmonitor-list-interfaces.php" \
        "/usr/local/sbin/gwmonitor-list-interfaces.php"
    install -m 0755 "${PLUGIN_DIR}/usr/local/sbin/gwmonitor-cleanup.php" \
        "/usr/local/sbin/gwmonitor-cleanup.php"

    # MVC model
    mkdir -p "${OPNSENSE_MVC}/models/OPNsense/GwMonitor/Menu"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/models/OPNsense/GwMonitor/Monitor.xml" \
        "${OPNSENSE_MVC}/models/OPNsense/GwMonitor/Monitor.xml"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/models/OPNsense/GwMonitor/Monitor.php" \
        "${OPNSENSE_MVC}/models/OPNsense/GwMonitor/Monitor.php"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/models/OPNsense/GwMonitor/Menu/Menu.xml" \
        "${OPNSENSE_MVC}/models/OPNsense/GwMonitor/Menu/Menu.xml"

    # MVC form
    mkdir -p "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/forms"
    install -m 0644 "${PLUGIN_DIR}/mvc/app/controllers/OPNsense/GwMonitor/forms/dialogMonitor.xml" \
        "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/forms/dialogMonitor.xml"

    # MVC controllers
    install -m 0644 "${PLUGIN_DIR}/mvc/app/controllers/OPNsense/GwMonitor/IndexController.php" \
        "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/IndexController.php"
    mkdir -p "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor/Api"
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

    echo "  Restarting configd..."
    /usr/local/sbin/pluginctl configd restart
    sleep 3

    echo "  Clearing caches..."
    rm -f /tmp/opnsense_menu_cache.xml
    rm -f /var/lib/php/tmp/opnsense_menu_cache.xml || true

    echo "  Starting monitors..."
    /usr/local/sbin/pluginctl -c monitor > /dev/null || true
    sleep 2
    /usr/local/sbin/gwmonitor-service.php reconfigure

    # Write version file
    install -m 0644 "${PLUGIN_DIR}/var/db/gwmonitor-version" \
        "/var/db/gwmonitor-version"

    echo "  Final configd restart..."
    service configd restart
    sleep 1

    echo ""
    echo "=== Installation complete (v${PLUGIN_VERSION}) ==="
    echo ""
    echo "Refresh browser (Ctrl+F5) and go to:"
    echo "  System → Gateways → Monitoring"
    echo ""
    echo "Add watchdog in Cron:"
    echo "  System → Settings → Cron → Command: GW Monitor Watchdog, all fields: *"
}

do_uninstall_silent() {
    # Silent removal during upgrade — always preserves settings
    pkill -f "gw_monitor_probe.py" > /dev/null || true
    sleep 1
    php /usr/local/sbin/gwmonitor-cleanup.php
    rm -f /usr/local/sbin/gw_monitor_probe.py
    rm -f /usr/local/sbin/gwmonitor-service.php
    rm -f /usr/local/sbin/gwmonitor-list-interfaces.php
    rm -f /usr/local/sbin/gwmonitor-cleanup.php
    rm -f /var/db/gwmonitor-version
    rm -rf "${OPNSENSE_MVC}/models/OPNsense/GwMonitor"
    rm -rf "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor"
    rm -rf "${OPNSENSE_MVC}/views/OPNsense/GwMonitor"
    rm -f /usr/local/etc/inc/plugins.inc.d/gw_monitor.inc
    rm -f /usr/local/opnsense/service/conf/actions.d/actions_gwmonitor.conf
    killall -9 gateway_watcher.php > /dev/null 2> /dev/null || true
    rm -f /tmp/gateways.status
    rm -f /tmp/opnsense_menu_cache.xml
    rm -f /var/lib/php/tmp/opnsense_menu_cache.xml || true
}

do_uninstall() {
    echo "=== Uninstalling os-gw-monitor ==="

    echo ""
    echo "What to do with monitor settings stored in config.xml?"
    echo "  [k] Keep   - settings will be restored on next install (default)"
    echo "  [d] Delete - remove all monitor instances from config.xml"
    echo ""
    printf "Choice [k/d]: "
    read PURGE_CHOICE

    echo "  Stopping monitors..."
    pkill -f "gw_monitor_probe.py" > /dev/null || true
    sleep 1

    echo "  Clearing gateway status and sockets..."
    case "$PURGE_CHOICE" in
        d|D)
            php /usr/local/sbin/gwmonitor-cleanup.php --purge
            ;;
        *)
            php /usr/local/sbin/gwmonitor-cleanup.php
            echo "  Settings kept in config.xml for future use."
            ;;
    esac

    echo "  Removing plugin files..."
    rm -f /usr/local/sbin/gw_monitor_probe.py
    rm -f /usr/local/sbin/gwmonitor-service.php
    rm -f /usr/local/sbin/gwmonitor-list-interfaces.php
    rm -f /usr/local/sbin/gwmonitor-cleanup.php
    rm -f /var/db/gwmonitor-version
    rm -rf "${OPNSENSE_MVC}/models/OPNsense/GwMonitor"
    rm -rf "${OPNSENSE_MVC}/controllers/OPNsense/GwMonitor"
    rm -rf "${OPNSENSE_MVC}/views/OPNsense/GwMonitor"
    rm -f /usr/local/etc/inc/plugins.inc.d/gw_monitor.inc
    rm -f /usr/local/opnsense/service/conf/actions.d/actions_gwmonitor.conf

    echo "  Restoring standard gateway monitoring..."
    killall -9 gateway_watcher.php > /dev/null 2> /dev/null || true
    rm -f /tmp/gateways.status
    sleep 1
    /usr/local/sbin/pluginctl -c monitor > /dev/null || true

    echo "  Clearing caches..."
    rm -f /tmp/opnsense_menu_cache.xml
    rm -f /var/lib/php/tmp/opnsense_menu_cache.xml || true
    rm -f /var/log/gwmonitor_*.log || true

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
