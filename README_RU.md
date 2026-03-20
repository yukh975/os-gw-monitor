🌐 [English](README.md) | **Русский**

---

# os-gw-monitor

[![Version](https://img.shields.io/badge/release-v1.1.0-blue)](https://github.com/yukh975/os-gw-monitor/releases)
[![Platform](https://img.shields.io/badge/platform-OPNsense%2025.x--26.x-blue)](https://opnsense.org)
[![FreeBSD](https://img.shields.io/badge/FreeBSD-14.3-red)](https://freebsd.org)
[![License](https://img.shields.io/badge/license-BSD--2--Clause-green)](https://github.com/yukh975/os-gw-monitor/blob/main/LICENSE.md)

**Плагин HTTP-мониторинга шлюзов для OPNsense.**

Мониторит шлюзы, которые не поддерживают ICMP, и отображает RTT, RTTd и Loss прямо в `System → Gateways → Configuration` — рядом со стандартными шлюзами.

Создан для туннельных интерфейсов: tun2socks, xray-core, sing-box, WireGuard и любых других, где ICMP недоступен или нежелателен.

---

## Как это работает

OPNsense использует `dpinger` для мониторинга шлюзов: отправляет ICMP-пакеты и читает результаты из Unix-сокета `/var/run/dpinger_<GW_NAME>.sock`. Этот плагин эмулирует такой сокет — вместо ICMP отправляет HTTP-запросы через `curl`, вычисляет RTT / RTTd / Loss по времени ответа и отвечает в точном формате, который ожидает OPNsense.

Стандартные шлюзы с ICMP-мониторингом продолжают работать параллельно и не затрагиваются.

```
curl  ──(HTTP)──▶  целевой хост
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

> `GW_NAME` — имя шлюза в настройках OPNsense.

---

## Возможности

| | |
|---|---|
| Несколько инстансов | Мониторинг любого числа шлюзов независимо друг от друга |
| Полный GUI | Управление мониторами в `System → Gateways → Monitoring` |
| Живые списки | Шлюзы и интерфейсы подгружаются из текущей конфигурации OPNsense |
| Без дублей | Один шлюз нельзя добавить дважды |
| Автозапуск | Мониторы перезапускаются при изменении настроек шлюзов |
| Watchdog | Cron-задача перезапускает упавшие мониторы |
| Безопасное обновление | Настройки сохраняются при установке новой версии |
| Безопасное удаление | Выбор: сохранить настройки или удалить полностью |

---

## Системные требования

| Компонент | Версия |
|-----------|--------|
| OPNsense  | 25.x — 26.x |
| Python    | 3.x *(встроен в OPNsense)* |
| curl      | *(встроен в OPNsense)* |

Внешних зависимостей нет. Ничего дополнительно устанавливать не нужно.

---

## Установка

```sh
git clone https://github.com/yukh975/os-gw-monitor
cd os-gw-monitor
sh install.sh
```

Либо скачайте [последний релиз](https://github.com/yukh975/os-gw-monitor/releases/latest), распакуйте и запустите `sh install.sh` из папки с файлами.

После установки нажмите **Ctrl+F5** для обновления браузера.

---

## Обновление

Запустите установщик — он сам определит текущую версию:

```sh
cd os-gw-monitor
git pull
sh install.sh
```

Скрипт удалит старую версию (настройки сохранятся) и установит новую. Если версия не изменилась — установка будет пропущена.

---

## Переустановка

Для переустановки текущей версии на месте — полезно после ручной правки файлов или при поломке установки:

```sh
sh install.sh reinstall
```

Настройки в `config.xml` сохраняются.

---

## Удаление

```sh
sh install.sh uninstall
```

Скрипт спросит, что делать с настройками мониторов в `config.xml`:

| Вариант | Результат |
|---------|-----------|
| `k` — Keep *(по умолчанию)* | Настройки остаются в `config.xml` и восстановятся при следующей установке |
| `d` — Delete | Настройки удаляются безвозвратно |

В обоих случаях: мониторы останавливаются, сокеты удаляются, штатный dpinger восстанавливается.

---

## Настройка

### Шаг 1 — Добавить мониторы

Перейдите в `System → Gateways → Monitoring`, нажмите **+** и заполните форму:

| Поле | Описание |
|------|----------|
| **Enabled** | Включить или отключить этот монитор |
| **Gateway Name** | Шлюз из `System → Gateways` |
| **Interface** | Сетевой интерфейс для зондирования |
| **Probe Host** | IP или хост для HTTP-запросов (например `1.1.1.1`) |
| **Probe Port** | TCP-порт (по умолчанию `80`) |
| **Probe Count** | Запросов за цикл — 1–20 (по умолчанию `5`) |
| **Interval (s)** | Интервал между циклами в секундах — 5–300 (по умолчанию `25`) |
| **Timeout (s)** | Таймаут одного запроса в секундах — 1–30 (по умолчанию `5`) |
| **Description** | Произвольная метка |

Нажмите **Apply** — монитор запустится немедленно.

### Шаг 2 — Отключить встроенный мониторинг шлюза

В `System → Gateways → Configuration` отредактируйте каждый шлюз, который вы мониторите этим плагином, и включите **Disable Gateway Monitoring**. Это предотвращает конфликт dpinger с сокетом плагина.

### Шаг 3 — Добавить watchdog

Перейдите в `System → Settings → Cron` и добавьте задачу:

| Поле | Значение |
|------|----------|
| Minutes | `*` |
| Hours | `*` |
| Day / Month / Weekday | `*` |
| Command | `Gateway Monitor Watchdog` |
| Parameters | *(оставить пустым)* |

Watchdog проверяет мониторы каждую минуту и перезапускает упавшие.

---

## Управление из командной строки

```sh
# Статус всех мониторов
configctl gwmonitor status

# Перезагрузить конфигурацию и перезапустить все мониторы
configctl gwmonitor reconfigure

# Запустить или остановить конкретный монитор по UUID
configctl gwmonitor start <uuid>
configctl gwmonitor stop <uuid>

# Запустить watchdog вручную
configctl gwmonitor watchdog

# Следить за логом конкретного шлюза
tail -f /var/log/gwmonitor_<GW_NAME>.log
```

---

## О метриках

Плагин измеряет `time_starttransfer` из `curl` — время до первого байта ответа (TTFB). Для туннельных протоколов это полный round-trip, включая установку соединения через туннель.

> Значения RTT будут выше, чем у ICMP-шлюзов — это ожидаемо. ICMP измеряет чистую сетевую задержку, TTFB включает TCP-рукопожатие и время обработки на сервере. Показания стабильны и одинаково применимы ко всем мониторам.

---

## Структура файлов

```
/usr/local/sbin/
├── gw_monitor_probe.py           # Демон зондирования + Unix-сокет сервер
├── gwmonitor-service.php         # Управление жизненным циклом инстансов
├── gwmonitor-list-interfaces.php # Список интерфейсов для GUI
└── gwmonitor-cleanup.php         # Очистка при удалении

/var/db/
└── gwmonitor-version             # Маркер установленной версии

/usr/local/etc/inc/plugins.inc.d/
└── gw_monitor.inc                # Регистрация плагина в OPNsense + хук monitor

/usr/local/opnsense/service/conf/actions.d/
└── actions_gwmonitor.conf        # Определения configd-действий

/usr/local/opnsense/mvc/app/
├── models/OPNsense/GwMonitor/    # Модель данных (XML + PHP)
├── controllers/OPNsense/GwMonitor/ # API и контроллеры страниц
└── views/OPNsense/GwMonitor/     # Volt-шаблон
```

---

## Автор

Юрий Хачатурян (при поддержке [Claude.AI](https://claude.ai)), 2026.

---

🌐 [English](README.md) | **Русский**
