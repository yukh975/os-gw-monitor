🌐 [English](README.md) | **Русский**

---

# os-gw-monitor 1.0.9

[![Version](https://img.shields.io/badge/release-v1.0.9-blue)](https://github.com/yukh975/os-gw-monitor/releases)
[![Platform](https://img.shields.io/badge/platform-OPNsense%2025.x--26.x-blue)](https://opnsense.org)
[![FreeBSD](https://img.shields.io/badge/FreeBSD-14.3-red)](https://freebsd.org)
[![License](https://img.shields.io/badge/license-BSD--2--Clause-green)](https://github.com/yukh975/os-gw-monitor/blob/main/LICENSE.md)

**HTTP-мониторинг шлюзов для OPNsense** — плагин для отображения RTT, RTTd и Loss в `System → Gateways → Configuration` для шлюзов, которые не поддерживают ICMP-мониторинг штатным `dpinger`.

Типичные случаи применения: tun2socks (xray-core, sing-box), а также любые туннельные интерфейсы где ICMP недоступен или нежелателен.

---

## Как это работает

OPNsense использует `dpinger` для мониторинга шлюзов — он отправляет ICMP-пакеты и читает результаты через Unix-сокет `/var/run/dpinger_<GW_NAME>.sock`. Плагин эмулирует этот сокет: вместо ICMP зондирует шлюз через HTTP с помощью `curl`, вычисляет RTT / RTTd / Loss и отвечает в формате, который OPNsense читает штатным механизмом `dpinger_status()`.

Штатные шлюзы с ICMP-мониторингом работают параллельно и не затрагиваются.

```
curl (HTTP probe)
    ↓ time_starttransfer
gw_monitor_probe.py
    ↓ Unix socket /var/run/dpinger_<GW_NAME>.sock
OPNsense dpinger_status()
    ↓
System → Gateways → Configuration (RTT / RTTd / Loss)
```

> **GW_NAME** — имя вашего шлюза в OPNsense.

---

## Возможности

- Мониторинг любого количества шлюзов через HTTP
- Настройка каждого инстанса через GUI: шлюз, интерфейс, хост, порт, количество проб, интервал, таймаут
- Выпадающие списки шлюзов и интерфейсов из текущей конфигурации OPNsense
- Защита от дублирования — один шлюз нельзя добавить дважды
- Статус отображается в `System → Gateways → Configuration` рядом со штатными шлюзами
- Автозапуск мониторов через plugin hook при изменении настроек шлюзов
- Watchdog через Cron — перезапускает упавшие мониторы каждую минуту
- Автообновление при установке новой версии с сохранением настроек
- При удалении — выбор: сохранить настройки для следующей установки или удалить полностью

---

## Системные требования

| Компонент | Версия |
|-----------|--------|
| OPNsense  | 25.x / 26.x |
| Python    | 3.x (встроен в OPNsense) |
| curl      | встроен в OPNsense |

---

## Установка

```sh
git clone https://github.com/yukh975/os-gw-monitor
cd os-gw-monitor
sh install.sh
```

Также можно скачать последний релиз, распаковать и установить вручную.

После завершения установки обновите браузер **Ctrl+F5**.

---

## Обновление

При установке новой версии поверх существующей скрипт автоматически:
1. Определяет текущую установленную версию
2. Выполняет тихое удаление с сохранением настроек
3. Устанавливает новую версию

```sh
cd os-gw-monitor
git pull
sh install.sh
```

Если версия не изменилась — установка будет пропущена.

---

## Удаление

```sh
sh install.sh uninstall
```

Скрипт спросит что делать с настройками:

- **[k] Keep** — настройки сохраняются в `config.xml` и восстановятся при следующей установке
- **[d] Delete** — настройки удаляются полностью

В обоих случаях: мониторы останавливаются, сокеты удаляются, штатный dpinger восстанавливается, кеш меню очищается.

---

## Настройка

### 1. Добавить мониторы

Перейдите в `System → Gateways → Monitoring`, нажмите **+** и заполните форму:

| Поле | Описание |
|------|----------|
| **Enabled** | Включить/выключить инстанс |
| **Gateway Name** | Шлюз из списка `System → Gateways` |
| **Interface** | Сетевой интерфейс для зондирования |
| **Probe Host** | IP или хост для HTTP-запроса (например `1.1.1.1`) |
| **Probe Port** | TCP-порт (по умолчанию `80`) |
| **Probe Count** | Количество проб за цикл (1–20, по умолчанию `5`) |
| **Interval (s)** | Интервал между циклами в секундах (5–300, по умолчанию `25`) |
| **Timeout (s)** | Таймаут одной пробы в секундах (1–30, по умолчанию `5`) |
| **Description** | Произвольное описание |

Нажмите **Apply** — мониторы запустятся автоматически.

### 2. Для AmneziaWG

В настройках шлюза `System → Gateways → GW_NAME → Edit` установите **Disable Gateway Monitoring** чтобы отключить штатный dpinger и избежать конфликта.

### 3. Добавить watchdog в Cron

`System → Settings → Cron → +`

| Поле | Значение |
|------|----------|
| Minutes | `*` |
| Hours | `*` |
| Day / Month / Week | `*` |
| Command | `Gateway Monitor Watchdog` |
| Parameters | *(пусто)* |

---

## Управление из командной строки

```sh
# Статус мониторов
configctl gwmonitor status

# Перезапустить все мониторы
configctl gwmonitor reconfigure

# Запустить/остановить конкретный инстанс
configctl gwmonitor start <uuid>
configctl gwmonitor stop <uuid>

# Watchdog вручную
configctl gwmonitor watchdog

# Логи
tail -f /var/log/gwmonitor_GW_NAME.log
```

---

## Структура файлов

```
/usr/local/sbin/
├── gw_monitor_probe.py           # Демон зондирования + Unix-сокет сервер
├── gwmonitor-service.php         # Бэкенд: управление инстансами
├── gwmonitor-list-interfaces.php # Список интерфейсов для GUI
└── gwmonitor-cleanup.php         # Очистка при удалении

/var/db/
└── gwmonitor-version             # Текущая версия плагина

/usr/local/etc/inc/plugins.inc.d/
└── gw_monitor.inc                # Регистрация в OPNsense + хук monitor

/usr/local/opnsense/service/conf/actions.d/
└── actions_gwmonitor.conf        # configd actions

/usr/local/opnsense/mvc/app/
├── models/OPNsense/GwMonitor/    # Модель данных
├── controllers/OPNsense/GwMonitor/ # API контроллеры
└── views/OPNsense/GwMonitor/     # Шаблон страницы
```

---

## Метрики

Плагин использует `curl --no-keepalive -w %{time_starttransfer}` — время до первого байта ответа. Для туннельных протоколов это реальная задержка приложения, которая включает установку соединения через туннель.

> Значения RTT будут выше чем у штатных ICMP-шлюзов — это нормально. ICMP измеряет сетевой RTT (1 round-trip), HTTP TTFB включает установку TCP-соединения и обработку запроса сервером.

---

## Лицензия

BSD 2-Clause License

Copyright (c) 2026 Yuriy Khachaturian

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.

---

## Автор

Юрий Хачатурян (при поддержке [Claude.AI](https://claude.ai)), 2026.

---

🌐 [English](README.md) | **Русский**
