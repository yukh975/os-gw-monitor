🌐 **English** | [Русский](CHANGELOG_RU.md)

---

# Changelog

## [1.1.0] — 2026-03-20

### Security
- 🔒 `gw_monitor_probe.py` — added connection semaphore (`_MAX_CONN = 10`) to prevent DoS via unlimited thread spawning on socket accept
- 🔒 `gw_monitor_probe.py` — socket connection timeout (`_CONN_TIMEOUT = 5s`) prevents hung clients from exhausting all semaphore slots
- 🔒 `gw_monitor_probe.py` — log file opened with `O_NOFOLLOW` atomically preventing TOCTOU symlink race
- 🔒 `gw_monitor_probe.py` — symlink attack protection on log file: process exits if log path is a symlink
- 🔒 `gw_monitor_probe.py` — Python process writes its own PID atomically at startup; eliminates unreliable `pgrep`/`ps` pattern matching in PHP
- 🔒 `gw_monitor_probe.py` — PID file created with permissions `0o600` (owner-only read)
- 🔒 `gw_monitor_probe.py` — `sock_path` (argv[1]) validated against `/var/run/dpinger_<name>.sock` pattern
- 🔒 `gw_monitor_probe.py` — added range validation for `count` (1–20), `interval` (5–300), `timeout` (1–30) with try/except on `int()` conversion
- 🔒 `gw_monitor_probe.py` — bare IPv6 addresses automatically wrapped in brackets in curl URL to prevent malformed requests
- 🔒 `gwmonitor-service.php` — `exec("kill")` replaced with `posix_kill(SIGTERM/SIGKILL)` for safe, fork-free process termination
- 🔒 `gwmonitor-service.php` — DNS rebinding protection: hostnames resolved once at validation time; resulting IP validated against blocked ranges
- 🔒 `gwmonitor-service.php` — added `stream_set_timeout(1)` on socket read to prevent indefinite blocking in `read_socket()`
- 🔒 `gwmonitor-service.php` — added `is_numeric()` validation on socket data parts before casting to int
- 🔒 `gwmonitor-service.php` — symlink check added before `fopen()` on reconfigure lock file
- 🔒 `gwmonitor-service.php` — reconfigure lock file removed after use (`@unlink`)
- 🔒 `gwmonitor-cleanup.php` — added 1 MB size cap before `unserialize()` to prevent memory exhaustion from crafted `gateways.status`
- 🔒 `gwmonitor-cleanup.php` — `simplexml_load_file()` return value checked; exits cleanly on parse failure
- 🔒 `gwmonitor-list-interfaces.php` — interface names from `ifconfig` validated against `[a-zA-Z0-9_-]+`
- 🔒 `MonitorController.php` — empty `gw_name` now returns a validation error in both `addMonitor` and `setMonitor` (previously bypassed duplicate check and fell through to model save)
- 🔒 `Monitor.xml` — `probe_host` mask extended to accept bracketed IPv6 addresses (`[2001:db8::1]`), consistent with PHP and Python validation layers
- 🔒 `install.sh` — `pkill -f` replaced with PID-file-based killing via `_kill_monitors()` to prevent accidentally killing unrelated processes
- 🔒 `install.sh` — `_kill_monitors()` now verifies via `ps` that the PID belongs to `gw_monitor_probe` before sending signal (prevents killing recycled PIDs)
- 🔒 `install.sh` — all `rm -f /tmp/opnsense_menu_cache.xml` calls now guarded with `[ ! -L ... ]` to prevent symlink-based file deletion
- 🔒 `gw_monitor.inc` — `shell_exec()` null return now detected and reported instead of silently ignored

### New Features
- ✨ `install.sh` — added `reinstall` command: silently removes current version (preserving settings) and reinstalls fresh, bypassing the same-version skip check

### Code Quality
- 🔧 `gwmonitor-cleanup.php` — replaced `goto` with structured `if/else` block

## [1.0.12] — 2026-03-20

### Security
- 🔒 TOCTOU fix in `read_socket()` — replaced `file_exists()` with atomic `lstat()` + `is_link()` check
- 🔒 `read_socket()` — added read limit (10 lines / 256 bytes each) to prevent DoS via oversized socket response
- 🔒 `stop_instance()` — `unlink()` now checks `is_link()` before removing pid/sock files
- 🔒 `stop_instance()` — replaced `pkill -f` pattern match with exact PID-based termination to prevent killing wrong process
- 🔒 `start_instance()` — `pgrep` result verified via `ps` exact path match to prevent process confusion
- 🔒 Unix socket permissions tightened to `0o600` (owner only)
- 🔒 SSRF — full IPv6 protection: blocked multicast (`ff00::/8`), link-local (`fe80::/10`), site-local (`fec0::/10`), unique-local (`fc00::/7`), IPv4-mapped addresses (`::ffff:127.x.x.x`), broadcast, reserved ranges
- 🔒 SSRF — Python probe blocks `is_reserved` and `is_multicast` via `ipaddress` module
- 🔒 Symlink attack protection — `unlink()` checks `is_link()` before removing socket/pid files
- 🔒 TOCTOU fix — socket existence check replaced with atomic `lstat()` + `S_IFSOCK` bitmask
- 🔒 Added exclusive lock on `reconfigure` to prevent race condition on concurrent calls
- 🔒 Added range validation for `probe_count` (1–20), `probe_interval` (5–300), `probe_timeout` (1–30) in PHP
- 🔒 Added input validation in Python probe for `gw_name`, `probe_host`, `probe_port`, `probe_if`
- 🔒 `probe_port` validated as integer 1–65535; `probe_if` validated against `[a-zA-Z0-9_]+` in PHP
- 🔒 `probe_host` blocked for loopback (127.0.0.0/8, ::1), link-local (169.254.0.0/16), unspecified in PHP and Python
- 🔒 Added `gw_name` validation against path traversal (`[a-zA-Z0-9_-]` only)
- 🔒 Added UUID format validation before passing to shell commands
- 🔒 Replaced `shell_exec("kill -0 ...")` with `posix_kill()`
- 🔒 Added file locking for `/tmp/gateways.status` operations
- 🔒 Added `htmlspecialchars()` on interface descriptions in API output
- 🔒 API error responses no longer expose raw configd output

### Bug Fixes
- 🐛 Fixed incorrect log filename (`tun2socks_socket.log` → `gwmonitor_<name>.log`)

## [1.0.7] — 2026-03-19

### Bug Fixes
- 🐛 Version file not deleted on uninstall

## [1.0.6] — 2026-03-19

### New Features
- ✨ English and Russian README with cross-links and badges
- ⚡ Version file moved to `/var/db/gwmonitor-version`

## [1.0.5] — 2026-03-18

### Documentation
- 📝 README documentation

## [1.0.4] — 2026-03-18

### Bug Fixes
- 🐛 Noisy output during uninstall

## [1.0.3] — 2026-03-18

### Bug Fixes
- 🐛 Noisy output during install and uninstall

## [1.0.2] — 2026-03-18

### New Features
- ✨ Version check and auto-upgrade on install
- ✨ Version displayed in GUI

## [1.0.1] — 2026-03-18

### New Features
- ✨ Initial release
- ✨ Multi-instance HTTP gateway monitoring
- ✨ GUI at System → Gateways → Monitoring
- ✨ Gateway and interface dropdowns
- ✨ Duplicate gateway protection
- ✨ Watchdog via Cron
- ✨ Install / uninstall script with settings preservation option

---

🌐 **English** | [Русский](CHANGELOG_RU.md)
