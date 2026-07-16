# Расуваефф/yii3-метрики
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-metrics.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-metrics.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-metrics/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-metrics)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-metrics/php)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-metrics.svg)](https://github.com/rasuvaeff/yii3-metrics/blob/master/LICENSE.md)
Независимые от поставщиков метрики для Yii3: фасад MetricRegistry над счетчиками, датчиками,
 и гистограммами, а также промежуточное программное обеспечение PSR-15 RED. Экспортером является заменяемый бэкэнд
 (сегодняшний Prometheus; заменяемый ключ провайдера оставляет место для других).

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) имеет компактную ссылку на API
 > которую можно передать в качестве контекста. @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - Интерфейсы PSR-7/PSR-15 (для промежуточного ПО RED)

## Установка
```bash
composer require rasuvaeff/yii3-metrics
```
Для реального экспорта добавьте бэкэнд: `rasuvaeff/yii3-metrics-prometheus`.
 Без него привяжите
 `MeterProviderInterface => NullMeterProvider` (см. [Wiring](#wiring-yiisoftconfig)). @@ЛИНИЯ@@
## Использование
### Запись показателей
```php
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;

/** @var MetricRegistry $registry (injected) */
$orders = $registry->counter('orders_total', 'Orders placed', ['channel']);
$orders->inc(1.0, new LabelSet(['channel' => 'web']));

$inflight = $registry->upDownCounter('inflight_jobs', 'Jobs in flight');
$inflight->add(1.0);   // job started
$inflight->add(-1.0);  // job finished

$temperature = $registry->gauge('room_temperature', 'Measured value');
$temperature->set(21.5);

$latency = $registry->histogram('db_query_seconds', 'Query time', ['op'], [0.001, 0.01, 0.1]);
$latency->observe(0.023, new LabelSet(['op' => 'select']));
```
Инструменты записываются в состояние накопления по имени — запрос
 `counter('orders_total')` снова возвращает инструмент в той же серии. Счетчик
 отклоняет отрицательное приращение.

 **Датчик против обратного счетчика.** Датчик предназначен для *измеренного абсолютного значения*
 (`set()` — температура, использование диска); счетчик вверх-вниз предназначен для *подсчета подъемов и
 падений* (`add(±δ)` — текущие запросы, размер пула). Для подсчитываемых значений предпочтительнее использовать счетчик вверх-вниз
: каждый процесс вносит дельты, поэтому он корректно агрегирует
 между кратковременными рабочими процессами php-fpm, где `inc()`/`dec()` датчика (сохраненные для удобства
 одного процесса) перезапустится с локального значения процесса. @@ЛИНИЯ@@
### Именование и метки
– Имена метрик соответствуют грамматике **Prometheus** `^[a-zA-Z_:][a-zA-Z0-9_:]*$`
 (подчеркивания, без точек) — наименьший общий знаменатель, отображаемый обоими серверными компонентами.
 — `LabelSet` проверяет имена меток (`^[a-zA-Z_]\w*$`) и сохраняет их в каноническом порядке
, поэтому равенство не зависит от порядка. @@ЛИНИЯ@@
### Промежуточное программное обеспечение RED
`RedMetricsMiddleware` (PSR-15) записывает для каждого запроса счетчик
 `http_server_requests_total` и гистограмму `http_server_request_duration_секундs`
, помеченную `method`, `route` и `status` (`500`, когда обработчик
 выбрасывает). @@ЛИНИЯ@@
```php
use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;

$middleware = new RedMetricsMiddleware($registry); // add to your PSR-15 stack

// Latency profile doesn't fit the Prometheus defaults (0.005s…10s)?
// Override the histogram bounds (seconds, strictly increasing; +Inf appended):
$middleware = new RedMetricsMiddleware($registry, durationBuckets: [0.1, 1.0, 10.0, 60.0]);

// Skip scrape/probe endpoints (exact paths) — their self-traffic is noise:
$middleware = new RedMetricsMiddleware($registry, excludedPaths: ['/metrics', '/health']);
```
При подключении `yiisoft/config` оба параметра берутся из параметров пакета:

```php
// config/common/params.php (app override)
'rasuvaeff/yii3-metrics' => [
    'red' => [
        'duration_buckets' => [0.1, 1.0, 10.0],
        'excluded_paths' => ['/metrics', '/health'],
    ],
],
```
> **Кардинальность:** метка `route` по умолчанию соответствует необработанному пути — новому временному ряду
 > для `/users/123`. В производство внедрите преобразователь с поддержкой маршрутизатора (ниже) или
 > санирующий преобразователь из серверной части Prometheus.

 Если установлен `yiisoft/router`, `CurrentRouteResolver` преобразует метку в
 **согласованный шаблон маршрута** (`/users/{id}`) — конструкция с низкой мощностью.
 Несовпадающие запросы (404, сканеры) сворачиваются в `(unmatched)`, если вы не передадите резервный преобразователь
:

```php
// config/common/di.php — app-side rebind (the core binds PathRouteResolver)
use Rasuvaeff\Yii3Metrics\CurrentRouteResolver;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;

return [
    RouteResolverInterface::class => CurrentRouteResolver::class,
];
```
Поместите RedMetricsMiddleware **перед** промежуточным программным обеспечением маршрутизатора — метка
 разрешается после запуска обработчика, когда заполняется CurrentRoute. @@ЛИНИЯ@@
### Проверка метрик в тестах
```php
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\MetricRegistry;

$provider = new InMemoryMeterProvider();
$registry = new MetricRegistry($provider);
$registry->counter('c')->inc();

$snapshots = $provider->snapshots(); // list<MetricSnapshot>, no timestamp
```
### поверхность API
| Тип | Роль |
 |---|---|
 | `Метрический реестр` | фасад: `counter/gauge/upDownCounter/histogram(name, help, labelNames, Buckets)` |
 | `MeterProviderInterface` / `MeterInterface` | заменяемая точка входа в серверную часть; метр создает и запоминает инструменты |
 | `CounterInterface` / `GaugeInterface` / `UpDownCounterInterface` / `HistogramInterface` | контракты на инструменты |
 | `LabelSet` / `MetricKind` | проверенные пары меток / перечисление типов инструментов (`Counter`, `Gauge`, `UpDownCounter`, `Histogram`) |
 | `MetricSnapshot` / `MetricSample` | собранное состояние: метрика (имя, вид, справка) и ее образцы для каждого набора меток |
 | `NullMeterProvider`, `NullMeter`, `NullCounter`, `NullGauge`, `NullUpDownCounter`, `NullHistogram` | бездействующий бэкэнд (по умолчанию только конфигурация; по-прежнему проверяет структуру) |
 | `InMemoryMeterProvider`, `InMemoryMeter`, `InMemoryCounter`, `InMemoryGauge`, `InMemoryUpDownCounter`, `InMemoryHistogram` | однопроцессная серверная часть разработки/тестирования с `snapshots()` |
 | `RedMetricsMiddleware`, `RouteResolverInterface`, `PathRouteResolver` | Приборы PSR-15 RED |
 | `CurrentRouteResolver` | метка маршрута из соответствующего шаблона `yiisoft/router` (необязательно) | @@ЛИНИЯ@@
## Проводка (`yiisoft/config`)
Ядро `config/di.php` связывает фасад ("MetricRegistry") и
 `RouteResolverInterface` по умолчанию. Он никогда не связывает MeterProviderInterface — этот заменяемый ключ
 принадлежит только одному источнику:
.
```php
// config/common/di.php — with no backend installed
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3Metrics\NullMeterProvider;

return [
    MeterProviderInterface::class => NullMeterProvider::class,
];
```
Установка бэкэнда обеспечивает реальную привязку — привязка его к двум пакетам поставщика
 является преднамеренной ошибкой `yiisoft/config` `Duplate key`. @@ЛИНИЯ@@
## Безопасность
- Имена меток проверены; **значения** меток произвольны — не допускайте использования
 высокомощных или конфиденциальных значений (идентификаторов, токенов) в метках.
 — КРАСНАЯ метка `route` по умолчанию указывает путь; продезинфицировать его на производстве. @@ЛИНИЯ@@
## Примеры
Запускаемые, независимые от сервера сценарии в [`examples/`](examples/). См.
 [`examples/README.md`](examples/README.md). @@ЛИНИЯ@@
## Разработка
```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```
Запускает проверку → нормализацию → require-checker → cs → psalm → тесты (включая тесты свойства
). См. [AGENTS.md](AGENTS.md). @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
