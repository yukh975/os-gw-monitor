🌐 **English** | [Русский](README_RU.md)

---

# os-gw-monitor 1.0.7

[![Version](https://img.shields.io/badge/release-v1.0.7-blue)](https://github.com/yukh975/os-gw-monitor/releases)
[![Platform](https://img.shields.io/badge/platform-OPNsense%2025.x--26.x-blue)](https://opnsense.org)
[![FreeBSD](https://img.shields.io/badge/FreeBSD-14.3-red)](https://freebsd.org)
[![License](https://img.shields.io/badge/license-BSD--2--Clause-green)](https://github.com/yukh975/os-gw-monitor/blob/main/LICENSE.md)

**HTTP-based gateway monitoring plugin for OPNsense** — displays RTT, RTTd and Loss in `System → Gateways → Configuration` for gateways that do not support ICMP monitoring via the built-in `dpinger`.

Typical use cases: tun2socks (xray-core, sing-box), and any tunnel interfaces where ICMP is unavailable or undesirable.

---

## How it works

OPNsense uses `dpinger` to monitor gateways — it sends ICMP packets and reads results from a Unix socket `/var/run/dpinger_<GW_NAME>.sock`. This plugin emulates that socket: instead of ICMP, it probes the gateway via HTTP using `curl`, calculates RTT / RTTd / Loss, and responds in the format that OPNsense reads through its standard `dpinger_status()` mechanism.

Standard ICMP-monitored gateways continue to work in parallel and are not affected.

```
curl (HTTP probe)
    ↓ time_starttransfer
gw_monitor_probe.py
    ↓ Unix socket /var/run/dpinger_<GW_NAME>.sock
OPNsense dpinger_status()
    ↓
System → Gateways → Configuration (RTT / RTTd / Loss)
```

---

## Features

- Monitor any number of gateways via HTTP
- Configure each instance through the GUI: gateway, interface, host, port, probe count, interval, timeout
- Dropdown lists of gateways and interfaces populated from the current OPNsense configuration
- Duplicate protection — the same gateway cannot be added twice
- Status displayed in `System → Gateways → Configuration` alongside standard gateways
- Auto-restart via plugin hook when gateway settings are changed
- Watchdog via Cron — restarts failed monitors every minute
- Auto-upgrade on new version install with settings preserved
- On removal — choose to keep settings for the next install or delete them completely

---

## Requirements

| Component | Version |
|-----------|---------|
| OPNsense  | 25.x / 26.x |
| Python    | 3.x (built into OPNsense) |
| curl      | built into OPNsense |

---

## Installation

```sh
git clone https://github.com/yukh975/os-gw-monitor
cd os-gw-monitor
sh install.sh
```

You can also download the latest release, extract it and install manually.

After installation completes, refresh your browser with **Ctrl+F5**.

---

## Configuration

### 1. Add monitors

Go to `System → Gateways → Monitoring`, click **+** and fill in the form:

| Field | Description |
|-------|-------------|
| **Enabled** | Enable or disable this instance |
| **Gateway Name** | Gateway from the `System → Gateways` list |
| **Interface** | Network interface to bind the curl probe |
| **Probe Host** | IP or hostname for the HTTP request (e.g. `1.1.1.1`) |
| **Probe Port** | TCP port (default: `80`) |
| **Probe Count** | Number of probes per cycle (1–20, default: `5`) |
| **Interval (s)** | Seconds between measurement cycles (5–300, default: `25`) |
| **Timeout (s)** | Timeout per probe in seconds (1–30, default: `5`) |
| **Description** | Optional description |

Click **Apply** — monitors will start automatically.

### 2. For AmneziaWG

In the gateway settings under `System → Gateways → GATEWAY_NAME → Edit`, enable **Disable Gateway Monitoring** to turn off the built-in dpinger and avoid conflicts.

### 3. Add watchdog to Cron

`System → Settings → Cron → +`

| Field | Value |
|-------|-------|
| Minutes | `*` |
| Hours | `*` |
| Day / Month / Week | `*` |
| Command | `Gateway Monitor Watchdog` |
| Parameters | *(empty)* |

---

## Upgrade

When installing a new version over an existing one, the script automatically:
1. Detects the currently installed version
2. Performs a silent removal while preserving settings
3. Installs the new version

```sh
cd os-gw-monitor
git pull
sh install.sh
```

If the version has not changed, the installation will be skipped.

---

## Removal

```sh
sh install.sh uninstall
```

The script will ask what to do with your settings:

- **[k] Keep** — settings are preserved in `config.xml` and will be restored on the next install
- **[d] Delete** — settings are permanently removed

In both cases: monitors are stopped, sockets are removed, standard dpinger is restored, and the menu cache is cleared.

---

## CLI management

```sh
# Monitor status
configctl gwmonitor status

# Restart all monitors
configctl gwmonitor reconfigure

# Start / stop a specific instance
configctl gwmonitor start <uuid>
configctl gwmonitor stop <uuid>

# Run watchdog manually
configctl gwmonitor watchdog

# Logs
tail -f /var/log/gwmonitor_GATEWAY_NAME.log
```

---

## File structure

```
/usr/local/sbin/
├── gw_monitor_probe.py           # Probe daemon + Unix socket server
├── gwmonitor-service.php         # Backend: instance management
├── gwmonitor-list-interfaces.php # Interface list for GUI dropdowns
└── gwmonitor-cleanup.php         # Cleanup on removal

/var/db/
└── gwmonitor-version             # Currently installed plugin version

/usr/local/etc/inc/plugins.inc.d/
└── gw_monitor.inc                # OPNsense registration + monitor hook

/usr/local/opnsense/service/conf/actions.d/
└── actions_gwmonitor.conf        # configd actions

/usr/local/opnsense/mvc/app/
├── models/OPNsense/GwMonitor/    # Data model
├── controllers/OPNsense/GwMonitor/ # API controllers
└── views/OPNsense/GwMonitor/     # Page template
```

---

## Metrics

The plugin uses `curl --no-keepalive -w %{time_starttransfer}` — time to first byte. For tunnel protocols this represents the real application-level latency, which includes establishing the connection through the tunnel.

> RTT values will be higher than for standard ICMP gateways — this is expected. ICMP measures network RTT (1 round-trip), while HTTP TTFB includes TCP connection setup and server response time.

---

## License

BSD 2-Clause License

Copyright (c) 2026 Yuriy Khachaturian

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.

---

## Author

Yuriy Khachaturian, 2026.

---

🌐 **English** | [Русский](README_RU.md)
