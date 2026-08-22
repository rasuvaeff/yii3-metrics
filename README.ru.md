# rasuvaeff/yii3-metrics

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-metrics.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-metrics.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-metrics/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-metrics)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-metrics/php)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-metrics.svg)](https://github.com/rasuvaeff/yii3-metrics/blob/master/LICENSE.md)
[English version](README.md)

Вендор-нейтральные метрики для Yii3: фасад `MetricRegistry` над counter'ами, gauge'ами
и histogram'ами, плюс PSR-15 RED middleware. Exporter — сменный backend
(сейчас Prometheus; сменный ключ provider'а оставляет место для других).

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник,
> который можно передать как контекст.
> Проекты с Composer-плагином [llm/skills](https://github.com/roxblnfk/skills)
> дополнительно получают agent-скилл этого пакета в `.agents/skills/`
> автоматически при установке.

## Требования

- PHP 8.3+
- Интерфейсы PSR-7/PSR-15 (для RED middleware)

## Установка

```bash
composer require rasuvaeff/yii3-metrics
```

Для реального экспорта добавьте backend: `rasuvaeff/yii3-metrics-prometheus`.
Без него забиндьте
`MeterProviderInterface => NullMeterProvider` (см. [Подключение](#подключение-yiisoftconfig)).

## Использование

### Запись метрик

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

Инструменты пишут в аккумулирующееся по имени состояние — повторный запрос
`counter('orders_total')` возвращает инструмент над тем же рядом. Counter
отклоняет отрицательный increment.

**Gauge vs up-down counter.** Gauge — для *измеренного абсолютного значения*
(`set()` — температура, использование диска); up-down counter — для *подсчёта
роста и падения* (`add(±δ)` — in-flight запросы, размер пула). Для подсчитываемых
значений предпочтительнее up-down counter: каждый процесс вносит дельты, поэтому
он корректно агрегируется между краткоживущими php-fpm worker'ами, где
`inc()`/`dec()` gauge'а (оставлены для удобства single-process) перезапускались бы
от process-local значения.

### Именование и лейблы

- Имена метрик следуют грамматике **Prometheus** `^[a-zA-Z_:][a-zA-Z0-9_:]*$`
  (подчёркивания, без точек) — наименьший общий знаменатель, который рендерят оба backend'а.
- `LabelSet` валидирует имена лейблов (`^[a-zA-Z_]\w*$`) и хранит их в каноническом
  порядке, поэтому равенство не зависит от порядка.
- `LabelSet::key()` — ключ агрегации. Каждое имя и значение записывается с
  префиксом длины (`<len>:<bytes>`), поэтому разные наборы лейблов всегда дают
  разные ключи, даже если значения содержат `=` или `,`. Конкретная строка —
  внутренняя деталь: сравнивайте наборы через `equals()`, а не по сохранённым
  где-то строкам `key()`.
- **Записываемые значения обязаны быть конечными.** `counter->inc()`,
  `histogram->observe()`, `upDownCounter->add()` и `gauge->inc()/dec()`
  отклоняют `NAN` и `±INF` через `Exception\InvalidArgumentException`: `NAN`
  поглощающий, и одна такая запись отравила бы серию на всё время жизни
  стораджа backend'а. `gauge->set()` — абсолютная запись, поэтому принимает
  `±INF` (в экспозиции есть токены `+Inf`/`-Inf`), но всё равно отклоняет `NAN`:
  promphp приводит его к невалидному токену и попутно поднимает PHP-warning.

### RED middleware

`RedMetricsMiddleware` (PSR-15) записывает для каждого запроса counter
`http_server_requests_total` и histogram
`http_server_request_duration_seconds`, помеченные `method`, `route` и
`status` (`500`, когда обработчик бросает исключение).

```php
use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;

$middleware = new RedMetricsMiddleware($registry); // add to your PSR-15 stack

// Latency profile doesn't fit the Prometheus defaults (0.005s…10s)?
// Override the histogram bounds (seconds, strictly increasing; +Inf appended):
$middleware = new RedMetricsMiddleware($registry, durationBuckets: [0.1, 1.0, 10.0, 60.0]);

// Skip scrape/probe endpoints (exact paths) — their self-traffic is noise:
$middleware = new RedMetricsMiddleware($registry, excludedPaths: ['/metrics', '/health']);
```

При подключении через `yiisoft/config` оба параметра берутся из params пакета:

```php
// config/common/params.php (app override)
'rasuvaeff/yii3-metrics' => [
    'red' => [
        'duration_buckets' => [0.1, 1.0, 10.0],
        'excluded_paths' => ['/metrics', '/health'],
    ],
],
```

#### Лейбл `route` — opt-in

> **Дефолт, который поставляется, вообще не читает URI запроса.** Без настройки
> лейбл `route` равен константе `(unset)` (`ConstantRouteResolver`). Rate, errors
> и duration по-прежнему разложены по `method` и `status`; выбрать нужно только
> разбивку по маршрутам.

Сырой путь не может быть безопасным дефолтом — он контролируется атакующим:

| Риск | Что происходит с сырым путём в `route` |
|---|---|
| Кардинальность | по серии на каждый просканированный URL (`/wp-admin/...`, `/.env`, `/users/123`). В общем promphp-сторадже эти серии живут до flush'а: APCu-сегмент забивается, память Redis и время скрейпа растут. |
| Утечка | `/reset-password/<token>` попадает в `/metrics` как есть — токен видит каждый, кто может прочитать endpoint. |

Выберите один из трёх резолверов, от самого безопасного к наименее:

```php
use Rasuvaeff\Yii3Metrics\{BoundedRouteResolver, CurrentRouteResolver, PathRouteResolver, RouteResolverInterface};

// 1. Паттерн сматченного маршрута ('/users/{id}') — низкая кардинальность по
//    построению. Несматченные запросы (404, сканеры) сворачиваются в
//    '(unmatched)'. Предпочтительный вариант.
RouteResolverInterface::class => CurrentRouteResolver::class,

// 2. Сырые пути с жёстким лимитом: первые N различных значений проходят,
//    остальные становятся '(other)'. Ограничивает число серий, но НЕ прячет
//    токены из пути.
RouteResolverInterface::class => static fn (): RouteResolverInterface
    => new BoundedRouteResolver(new PathRouteResolver(), limit: 100),

// 3. Сырые пути без ограничения — только там, где пространство путей мало и
//    заведомо не содержит секретов.
RouteResolverInterface::class => PathRouteResolver::class,
```

Лимит варианта 2 действует **на экземпляр резолвера**, а экземпляр живёт в одном
процессе: на php-fpm каждый воркер узнаёт свой набор различных путей, поэтому
худший случай — `limit × workers` серий. Это сходящаяся граница, а не
деплой-глобальная гарантия кардинальности.

Prometheus-backend дополнительно даёт `SanitizingRouteResolver`, который
схлопывает числовые id и UUID в сыром пути. Он закрывает только случай id —
произвольные сканерные пути и токены не-UUID-формата остаются уникальными,
поэтому считайте его уточнением варианта 3, а не заменой вариантам 1–2.

`CurrentRouteResolver` требует `yiisoft/router`. Помещайте `RedMetricsMiddleware`
**до** router middleware — лейбл резолвится после отработки обработчика, когда
`CurrentRoute` заполнен.

### Инспекция метрик в тестах

```php
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\MetricRegistry;

$provider = new InMemoryMeterProvider();
$registry = new MetricRegistry($provider);
$registry->counter('c')->inc();

$snapshots = $provider->snapshots(); // list<MetricSnapshot>, no timestamp
```

### API surface

| Тип | Роль |
|---|---|
| `MetricRegistry` | фасад: `counter/gauge/upDownCounter/histogram(name, help, labelNames, buckets)` |
| `MeterProviderInterface` / `MeterInterface` | точка входа сменного backend'а; meter создаёт и мемоизирует инструменты |
| `CounterInterface` / `GaugeInterface` / `UpDownCounterInterface` / `HistogramInterface` | контракты инструментов |
| `LabelSet` / `MetricKind` | валидируемые пары лейблов / enum вида инструмента (`Counter`, `Gauge`, `UpDownCounter`, `Histogram`) |
| `MetricSnapshot` / `MetricSample` | собранное состояние: метрика (name, kind, help) и её сэмплы по каждому набору лейблов |
| `NullMeterProvider`, `NullMeter`, `NullCounter`, `NullGauge`, `NullUpDownCounter`, `NullHistogram` | no-op backend (config-only по умолчанию; всё равно валидирует структуру) |
| `InMemoryMeterProvider`, `InMemoryMeter`, `InMemoryCounter`, `InMemoryGauge`, `InMemoryUpDownCounter`, `InMemoryHistogram` | single-process dev/test backend с `snapshots()` |
| `RedMetricsMiddleware`, `RouteResolverInterface` | PSR-15 RED-инструментирование |
| `ConstantRouteResolver` | безопасный дефолт лейбла `route`: константа, никогда не выводится из запроса |
| `PathRouteResolver`, `BoundedRouteResolver` | opt-in лейбл из сырого пути; bounded-декоратор ограничивает число различных значений |
| `CurrentRouteResolver` | лейбл маршрута из сматченного паттерна `yiisoft/router` (optional dep) |
| `Buckets` | общие раскладки бакетов гистограммы (`Buckets::PROMETHEUS_DEFAULTS`, секунды, без хвостового `+Inf`) |

## Подключение (`yiisoft/config`)

Ядро `config/di.php` биндит фасад (`MetricRegistry`) и `RouteResolverInterface`
по умолчанию (`ConstantRouteResolver` — см. «Лейбл `route` — opt-in»). Оно никогда не биндит `MeterProviderInterface` — этот сменный ключ
принадлежит ровно одному источнику:

```php
// config/common/di.php — with no backend installed
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3Metrics\NullMeterProvider;

return [
    MeterProviderInterface::class => NullMeterProvider::class,
];
```

Установка backend'а даёт реальный биндинг — биндинг в двух vendor-пакетах это
намеренная ошибка `yiisoft/config` `Duplicate key`.

## Безопасность

- Имена лейблов валидируются; **значения** лейблов произвольны — не кладите
  high-cardinality или чувствительные значения (id, токены) в лейблы.
- RED-лейбл `route` — **opt-in**: поставляемый дефолт равен константе `(unset)`
  именно для того, чтобы контролируемый атакующим путь не плодил серии и не
  уносил одноразовый токен в `/metrics`. См. «Лейбл `route` — opt-in» перед
  включением лейбла из пути.
- Endpoint экспозиции не имеет собственного контроля доступа — закрывайте
  `/metrics` на уровне edge/роутера.

## Примеры

Запускаемые, server-independent скрипты в [`examples/`](examples/). См.
[`examples/README.md`](examples/README.md).

## Разработка

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Прогоняет validate → normalize → require-checker → cs → psalm → тесты (включая
property-тесты). См. [AGENTS.md](AGENTS.md).

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
