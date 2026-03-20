🌐 **English** | [Русский](CHANGELOG_RU.md)

---

# Changelog

## [1.1.0] — 2026-03-20
- 🚀 Development version on devel branch
- 🔒 Security: `gw_monitor_probe.py` — added connection semaphore (`_MAX_CONN = 10`) to prevent DoS via unlimited thread spawning on socket accept
- 🔒 Security: `gwmonitor-cleanup.php` — added 1 MB size cap before `unserialize()` to prevent memory exhaustion from crafted `gateways.status`
- 🔒 Security: `MonitorController.php` — empty `gw_name` now returns a validation error in both `addMonitor` and `setMonitor` (previously bypassed duplicate check and fell through to model save)
- 🔒 Security: `install.sh` — all `rm -f /tmp/opnsense_menu_cache.xml` calls now guarded with `[ ! -L ... ]` to prevent symlink-based file deletion
- 🔒 Security: `Monitor.xml` — `probe_host` mask extended to accept bracketed IPv6 addresses (`[2001:db8::1]`), consistent with PHP and Python validation layers
- 🔒 Security: `gw_monitor_probe.py` — symlink attack protection on log file: process exits if log path is a symlink
- 🔒 Security: `gw_monitor_probe.py` — socket connection timeout (`_CONN_TIMEOUT = 5s`) prevents hung clients from exhausting all semaphore slots
- 🔒 Security: `gw_monitor_probe.py` — Python process writes its own PID atomically at startup; eliminates unreliable `pgrep`/`ps` pattern matching in PHP
- 🔒 Security: `gwmonitor-service.php` — `exec("kill")` replaced with `posix_kill(SIGTERM/SIGKILL)` for safe, fork-free process termination
- 🔒 Security: `gwmonitor-service.php` — DNS rebinding protection: hostnames resolved once at validation time; resulting IP validated against blocked ranges
- 🔒 Security: `gw_monitor_probe.py` — added range validation for `count` (1–20), `interval` (5–300), `timeout` (1–30) with try/except on `int()` conversion
- 🔒 Security: `gw_monitor_probe.py` — PID file created with permissions `0o600` (owner-only read)
- 🔒 Security: `gwmonitor-service.php` — added `stream_set_timeout(1)` on socket read to prevent indefinite blocking in `read_socket()`
- 🔒 Security: `gwmonitor-service.php` — added `is_numeric()` validation on socket data parts before casting to int
- 🔒 Security: `install.sh` — `pkill -f` replaced with PID-file-based killing via `_kill_monitors()` to prevent accidentally killing unrelated processes
- 🔒 Security: `install.sh` — `_kill_monitors()` now verifies via `ps` that the PID belongs to `gw_monitor_probe` before sending signal (prevents killing recycled PIDs)
- 🔒 Security: `gw_monitor_probe.py` — bare IPv6 addresses automatically wrapped in brackets in curl URL to prevent malformed requests
- 🔒 Security: `gwmonitor-service.php` — reconfigure lock file removed after use (`@unlink`)
- 🔒 Security: `gwmonitor-service.php` — symlink check added before `fopen()` on reconfigure lock file
- 🔒 Security: `gw_monitor_probe.py` — `sock_path` (argv[1]) validated against `/var/run/dpinger_<name>.sock` pattern
- 🔒 Security: `gw_monitor_probe.py` — log file opened with `O_NOFOLLOW` atomically preventing TOCTOU symlink race
- 🔒 Security: `gwmonitor-list-interfaces.php` — interface names from `ifconfig` validated against `[a-zA-Z0-9_-]+`
- 🔒 Security: `gwmonitor-cleanup.php` — `simplexml_load_file()` return value checked; exits cleanly on parse failure
- 🔒 Security: `install.sh` — `_kill_monitors()` grep tightened to `python3.*gw_monitor_probe\.py` (exact match)
- 🔒 Security: `gw_monitor.inc` — `shell_exec()` null return now detected and reported instead of silently ignored
- 🔧 Code: `gwmonitor-cleanup.php` — replaced `goto` with structured `if/else` block
- ✨ Feature: `install.sh` — added `reinstall` command: silently removes current version (preserving settings) and reinstalls fresh, bypassing the same-version skip check

## [1.0.12] — 2026-03-20
- 🔒 Security: TOCTOU fix in `read_socket()` — replaced `file_exists()` with atomic `lstat()` + `is_link()` check
- 🔒 Security: `read_socket()` — added read limit (10 lines / 256 bytes each) to prevent DoS via oversized socket response
- 🔒 Security: `stop_instance()` — `unlink()` now checks `is_link()` before removing pid/sock files
- 🔒 Security: `stop_instance()` — replaced `pkill -f` pattern match with exact PID-based termination to prevent killing wrong process
- 🔒 Security: `start_instance()` — `pgrep` result verified via `ps` exact path match to prevent process confusion
- 🔒 Security: Unix socket permissions tightened to `0o600` (owner only)
- 🔒 Security: SSRF — full IPv6 protection: blocked multicast (`ff00::/8`), link-local (`fe80::/10`), site-local (`fec0::/10`), unique-local (`fc00::/7`), IPv4-mapped addresses (`::ffff:127.x.x.x`), broadcast, reserved ranges
- 🔒 Security: SSRF — Python probe blocks `is_reserved` and `is_multicast` via `ipaddress` module
- 🔒 Security: Symlink attack protection — `unlink()` checks `is_link()` before removing socket/pid files
- 🔒 Security: TOCTOU fix — socket existence check replaced with atomic `lstat()` + `S_IFSOCK` bitmask
- 🔒 Security: Added exclusive lock on `reconfigure` to prevent race condition on concurrent calls
- 🔒 Security: Added range validation for `probe_count` (1–20), `probe_interval` (5–300), `probe_timeout` (1–30) in PHP
- 🔒 Security: Added input validation in Python probe for `gw_name`, `probe_host`, `probe_port`, `probe_if`
- 🔒 Security: `probe_port` validated as integer 1–65535; `probe_if` validated against `[a-zA-Z0-9_]+` in PHP
- 🔒 Security: `probe_host` blocked for loopback (127.0.0.0/8, ::1), link-local (169.254.0.0/16), unspecified in PHP and Python
- 🔒 Security: Added `gw_name` validation against path traversal (`[a-zA-Z0-9_-]` only)
- 🔒 Security: Added UUID format validation before passing to shell commands
- 🔒 Security: Replaced `shell_exec("kill -0 ...")` with `posix_kill()`
- 🔒 Security: Added file locking for `/tmp/gateways.status` operations
- 🔒 Security: Added `htmlspecialchars()` on interface descriptions in API output
- 🔒 Security: API error responses no longer expose raw configd output
- 🐛 Fixed incorrect log filename (`tun2socks_socket.log` → `gwmonitor_<name>.log`)

## [1.0.7] — 2026-03-19
- 🐛 Version file not deleted on uninstall

## [1.0.6] — 2026-03-19
- ✨ English and Russian README with cross-links and badges
- ⚡ Version file moved to `/var/db/gwmonitor-version`

## [1.0.5] — 2026-03-18
- 📝 README documentation

## [1.0.4] — 2026-03-18
- 🐛 Noisy output during uninstall

## [1.0.3] — 2026-03-18
- 🐛 Noisy output during install and uninstall

## [1.0.2] — 2026-03-18
- ✨ Version check and auto-upgrade on install
- ✨ Version displayed in GUI

## [1.0.1] — 2026-03-18
- ✨ Initial release
- ✨ Multi-instance HTTP gateway monitoring
- ✨ GUI at System → Gateways → Monitoring
- ✨ Gateway and interface dropdowns
- ✨ Duplicate gateway protection
- ✨ Watchdog via Cron
- ✨ Install / uninstall script with settings preservation option

---

🌐 **English** | [Русский](CHANGELOG_RU.md)
