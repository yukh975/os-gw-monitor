🌐 **English** | [Русский](CHANGELOG_RU.md)

---

# Changelog

## [1.0.8] — 2026-03-20
- 🔒 Security: Unix socket permissions changed from `0o666` to `0o660`
- 🔒 Security: Fixed TOCTOU race condition on socket creation
- 🔒 Security: Added `gw_name` validation against path traversal (`[a-zA-Z0-9_-]` only)
- 🔒 Security: Added UUID format validation before passing to shell commands
- 🔒 Security: Replaced `shell_exec("kill -0 ...")` with `posix_kill()`
- 🔒 Security: Added file locking for `/tmp/gateways.status` operations
- 🔒 Security: Added `htmlspecialchars()` on interface descriptions in API output

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
