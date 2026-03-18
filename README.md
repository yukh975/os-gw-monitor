# os-gw-monitor

Плагин для OPNsense: HTTP-мониторинг шлюзов через GUI.
Отображает RTT / RTTd / Loss в `System → Gateways → Configuration`
для шлюзов которые не поддерживают ICMP (tun2socks, AmneziaWG и др.).

## Установка

```sh
fetch -o /tmp/os-gw-monitor.tar https://github.com/.../os-gw-monitor.tar
cd /tmp && tar xf os-gw-monitor.tar && cd os-gw-monitor
sh install.sh
```

## Использование

1. `System → Gateways → Monitoring` → кнопка `+`
2. Заполнить: Gateway Name, Interface, Probe Host, Port, Count, Interval, Timeout
3. Нажать **Apply**
4. Добавить watchdog: `System → Settings → Cron` → Command: `GW Monitor Watchdog`, все поля `*`

## Удаление

```sh
sh install.sh uninstall
```
