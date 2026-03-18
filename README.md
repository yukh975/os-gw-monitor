# os-gw-monitor

Плагин для OPNsense: HTTP-мониторинг шлюзов через GUI.
Отображает RTT / RTTd / Loss в `System → Gateways → Configuration`
для шлюзов которые не поддерживают ICMP.

## Установка

```sh
git clone https://github.com/yukh975/os-gw-monitor/
cd os-gw-monitor
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
