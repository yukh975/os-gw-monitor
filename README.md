🌐 **English** | [Русский](README_RU.md)

---

# os-gw-monitor

[![Version](https://img.shields.io/badge/release-v1.1.0-blue)](https://github.com/yukh975/os-gw-monitor/releases)
[![Platform](https://img.shields.io/badge/platform-OPNsense%2025.x--26.x-blue)](https://opnsense.org)
[![FreeBSD](https://img.shields.io/badge/FreeBSD-14.3-red)](https://freebsd.org)
[![License](https://img.shields.io/badge/license-BSD--2--Clause-green)](https://github.com/yukh975/os-gw-monitor/blob/main/LICENSE.md)

**HTTP gateway monitoring plugin for OPNsense.**

Monitors gateways that don't support ICMP and shows RTT, RTTd and Loss right in `System → Gateways → Configuration` — the same place as standard gateways.

Designed for tunnel interfaces: tun2socks, xray-core, sing-box, WireGuard, and anything else where ICMP is unavailable or undesirable.

---

## How it works

OPNsense uses `dpinger` to monitor gateways: it sends ICMP packets and reads results from a Unix socket at `/var/run/dpinger_<GW_NAME>.sock`. This plugin emulates that socket — instead of ICMP, it sends HTTP requests via `curl`, computes RTT / RTTd / Loss from the response time, and replies in the exact format OPNsense expects.

Standard ICMP gateways are not affected and continue to work alongside.

```
curl  ──(HTTP)──▶  target host
  time_starttransfer
        │
  gw_monitor_probe.py
        │
  /var/run/dpinger_<GW_NAME>.sock
        │
  OPNsense dpinger_status()
        │
  System → Gateways → Configuration
    RTT / RTTd / Loss
```

> `GW_NAME` is the gateway name as configured in OPNsense.

---

## Features

| | |
|---|---|
| Multi-instance | Monitor any number of gateways independently |
| Full GUI | Add and configure monitors at `System → Gateways → Monitoring` |
| Live dropdowns | Gateway and interface lists pulled from current OPNsense config |
| No duplicates | The same gateway cannot be added twice |
| Auto-start | Monitors restart automatically when gateway settings change |
| Watchdog | Cron-based watchdog restarts crashed monitors |
| Safe upgrades | New version installs preserve existing settings |
| Safe removal | Choose to keep or delete settings on uninstall |

---

## Requirements

| Component | Version |
|-----------|---------|
| OPNsense  | 25.x — 26.x |
| Python    | 3.x *(built into OPNsense)* |
| curl      | *(built into OPNsense)* |

No external dependencies. No packages to install.

---

## Installation

```sh
git clone https://github.com/yukh975/os-gw-monitor
cd os-gw-monitor
sh install.sh
```

Alternatively, download and extract the [latest release](https://github.com/yukh975/os-gw-monitor/releases/latest) and run `sh install.sh` from the extracted directory.

After installation, press **Ctrl+F5** to refresh the browser.

---

## Upgrade

Run the installer — it detects the current version automatically:

```sh
cd os-gw-monitor
git pull
sh install.sh
```

The script removes the old version (settings are preserved), then installs the new one. If the version hasn't changed, installation is skipped.

---

## Reinstall

To reinstall the current version in place — useful after manual file edits or to recover a broken installation:

```sh
sh install.sh reinstall
```

Settings in `config.xml` are preserved.

---

## Removal

```sh
sh install.sh uninstall
```

You will be asked what to do with monitor settings stored in `config.xml`:

| Option | Effect |
|--------|--------|
| `k` — Keep *(default)* | Settings stay in `config.xml` and are restored on next install |
| `d` — Delete | Settings are permanently removed |

In both cases: monitors are stopped, sockets are cleaned up, and the standard dpinger is restored.

---

## Configuration

### Step 1 — Add monitors

Go to `System → Gateways → Monitoring`, click **+** and fill in the form:

| Field | Description |
|-------|-------------|
| **Enabled** | Enable or disable this monitor |
| **Gateway Name** | Gateway from `System → Gateways` |
| **Interface** | Network interface used for probing |
| **Probe Host** | IP or hostname to send HTTP requests to (e.g. `1.1.1.1`) |
| **Probe Port** | TCP port (default: `80`) |
| **Probe Count** | Requests per cycle — 1–20 (default: `5`) |
| **Interval (s)** | Seconds between cycles — 5–300 (default: `25`) |
| **Timeout (s)** | Per-request timeout in seconds — 1–30 (default: `5`) |
| **Description** | Optional label |

Click **Apply** to start the monitor immediately.

### Step 2 — Disable built-in monitoring for the gateway

In `System → Gateways → Configuration`, edit each gateway you are monitoring with this plugin and enable **Disable Gateway Monitoring**. This prevents dpinger from conflicting with the plugin's socket.

### Step 3 — Add a watchdog

Go to `System → Settings → Cron` and add a new job:

| Field | Value |
|-------|-------|
| Minutes | `*` |
| Hours | `*` |
| Day / Month / Weekday | `*` |
| Command | `Gateway Monitor Watchdog` |
| Parameters | *(leave empty)* |

The watchdog checks every minute and restarts any monitor that has stopped unexpectedly.

---

## CLI reference

```sh
# Show status of all monitors
configctl gwmonitor status

# Reload configuration and restart all monitors
configctl gwmonitor reconfigure

# Start or stop a specific monitor by UUID
configctl gwmonitor start <uuid>
configctl gwmonitor stop <uuid>

# Run the watchdog manually
configctl gwmonitor watchdog

# Follow logs for a specific gateway
tail -f /var/log/gwmonitor_<GW_NAME>.log
```

---

## About the metrics

The plugin measures `time_starttransfer` from `curl` — time to first byte (TTFB). For tunnel protocols, this reflects the full round-trip including connection setup through the tunnel.

> RTT values will naturally be higher than ICMP: ICMP measures raw network latency, while TTFB includes TCP handshake and server processing time. This is expected and consistent across all monitors.

---

## File layout

```
/usr/local/sbin/
├── gw_monitor_probe.py           # Probe daemon + Unix socket server
├── gwmonitor-service.php         # Instance lifecycle management
├── gwmonitor-list-interfaces.php # Interface list for GUI dropdowns
└── gwmonitor-cleanup.php         # Cleanup on uninstall

/var/db/
└── gwmonitor-version             # Installed version marker

/usr/local/etc/inc/plugins.inc.d/
└── gw_monitor.inc                # OPNsense plugin registration + monitor hook

/usr/local/opnsense/service/conf/actions.d/
└── actions_gwmonitor.conf        # configd action definitions

/usr/local/opnsense/mvc/app/
├── models/OPNsense/GwMonitor/    # Data model (XML + PHP)
├── controllers/OPNsense/GwMonitor/ # API and page controllers
└── views/OPNsense/GwMonitor/     # Volt template
```

---

## Author

Yuriy Khachaturian (powered by [Claude.AI](https://claude.ai)), 2026.

---

🌐 **English** | [Русский](README_RU.md)
