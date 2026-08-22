# Upgrade to 2.0

Two behaviour changes in 2.0.0 may require action in your application. Both are
invisible at install time: composer resolves, the container builds, tests on
finite input pass.

## The `route` label no longer contains the request path

The shipped default resolver used to copy the raw request URI into the `route`
label of every RED metric. In 2.0 the default is `ConstantRouteResolver`, which
returns `(unset)` and never reads the URI. Rate/errors/duration metrics keep
their `method`/`status` breakdown; only the `route` label changes.

**Action:** if dashboards or alerts group by `route`, opt into a route
breakdown by rebinding `RouteResolverInterface` (config/common/di.php):

```php
use Rasuvaeff\Yii3Metrics\{BoundedRouteResolver, CurrentRouteResolver, PathRouteResolver, RouteResolverInterface};

// Matched router pattern ('/users/{id}'), low-cardinality by construction. Preferred.
RouteResolverInterface::class => CurrentRouteResolver::class,

// Raw paths capped at N distinct values per process; overflow becomes '(other)'.
// Does NOT hide path tokens.
RouteResolverInterface::class => static fn (): RouteResolverInterface
    => new BoundedRouteResolver(new PathRouteResolver(), limit: 100),

// Raw paths, unbounded - only where the path space is small and secret-free.
RouteResolverInterface::class => PathRouteResolver::class,
```

If you did nothing custom before, no change is required: you were getting raw
paths by accident, and the new default is strictly safer.

## Non-finite recordings now throw instead of being stored

`inc()`, `observe()`, `add()` and gauge `inc()`/`dec()` throw
`Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException` on `NAN` and `±INF`;
gauge `set()` throws on `NAN` (it still accepts `±INF`). Previously such values
passed the guards and poisoned the affected series permanently (`NAN + x ===
NAN`), or broke the histogram invariant `count == bucket{le="+Inf"}`.

**Action:** if any recorded amount can be computed from division, logs, or
external input, either fix the source or filter it yourself:

```php
if (is_finite($amount)) {
    $counter->inc($amount);
}
```

Letting the exception surface is usually the better option: a rejected
recording is loud, a poisoned series is silent.

## Not breaking, but worth knowing

- `LabelSet::key()` changed its encoding (length-prefixed). It is internal
  aggregation state, but anything that persisted keys across the upgrade will
  see different key strings for the same labels.
- `Buckets::PROMETHEUS_DEFAULTS` is now public API — backends should read the
  shared default bounds instead of hardcoding their own scale.
