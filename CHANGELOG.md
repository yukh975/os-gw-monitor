🌐 **English** | [Русский](CHANGELOG_RU.md)

---

# Changelog

All notable changes to os-gw-monitor are documented here.

---

## [1.0.7] — 2026-03-19

### Fixed
- Version file `/var/db/gwmonitor-version` was not deleted on uninstall, causing the installer to incorrectly detect the plugin as still installed on subsequent installs

---

## [1.0.6] — 2026-03-19

### Added
- `README_RU.md` — Russian documentation with cross-links to English version
- Badges (version, platform, license) in both README files

### Changed
- `README.md` translated to English as the primary documentation file
- Version file moved from `/usr/local/sbin/gwmonitor-version` to `/var/db/gwmonitor-version`
- Author name added to README files

### Removed
- `plugin/usr/local/sbin/gwmonitor-version` — replaced by `plugin/var/db/gwmonitor-version`

---

## [1.0.5] — 2026-03-19

### Added
- `README.md` — full plugin documentation (Russian)

### Changed
- All output from `pkill`, `killall`, and `pluginctl` suppressed during silent uninstall

---

## [1.0.4] — 2026-03-19

### Fixed
- `killall gateway_watcher.php` output (`No matching processes were found`) no longer shown during uninstall — both stdout and stderr now suppressed

---

## [1.0.3] — 2026-03-19

### Fixed
- Noisy output from `pkill`, `killall`, and `pluginctl -c monitor` suppressed during install and uninstall

---

## [1.0.2] — 2026-03-19

### Added
- Version check on install: if the same version is already installed, installation is skipped
- Auto-upgrade: if a different version is detected, silent uninstall (keeping settings) runs before installing the new version
- `do_uninstall_silent` function for upgrade flow
- Version stored in `/var/db/gwmonitor-version`
- Version displayed on the Monitoring page in the GUI

---

## [1.0.1] — 2026-03-19

### Added
- Initial plugin release
- Multi-instance HTTP gateway monitoring via `curl`
- GUI at `System → Gateways → Monitoring` (MVC plugin with UIBootgrid table)
- Per-instance settings: gateway name, interface, probe host, port, count, interval, timeout, description
- Gateway and interface dropdowns populated from OPNsense configuration
- Duplicate gateway protection in `MonitorController`
- `gw_monitor_probe.py` — Python daemon: HTTP probing + dpinger-compatible Unix socket server
- `gwmonitor-service.php` — backend: reads config.xml, starts/stops instances
- `gwmonitor-list-interfaces.php` — returns physical interface list for GUI dropdowns
- `gwmonitor-cleanup.php` — cleans up sockets, pid files, and gateway status cache on removal
- `gw_monitor.inc` — OPNsense plugin hook: registers services and `monitor` configure hook for auto-restart after gateway settings changes
- `actions_gwmonitor.conf` — configd actions: reconfigure, status, start, stop, watchdog, listinterfaces
- `install.sh` with install / uninstall modes
- On uninstall: choice to keep or delete settings from config.xml
- On uninstall: gateway status cache cleared, sockets removed, standard dpinger restored
- Menu cache cleared on both install and uninstall
- Second `configd restart` at end of install to ensure all actions are loaded
- Watchdog support via OPNsense Cron (`Gateway Monitor Watchdog`)

---

🌐 **English** | [Русский](CHANGELOG_RU.md)
