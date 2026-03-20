🌐 **English** | [Русский](CHANGELOG_RU.md)

---

# Changelog

## [1.0.12] — 2026-03-20
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
