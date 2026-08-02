[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/telemetry-otel/require/php)](https://packagist.org/packages/waffle-commons/telemetry-otel)
[![PHP CI](https://github.com/waffle-commons/telemetry-otel/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/telemetry-otel/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/telemetry-otel/graph/badge.svg)](https://codecov.io/gh/waffle-commons/telemetry-otel)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/telemetry-otel/v)](https://packagist.org/packages/waffle-commons/telemetry-otel)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/telemetry-otel)](https://github.com/waffle-commons/telemetry-otel/blob/main/LICENSE.md)

Waffle Telemetry — OpenTelemetry Bridge
=========================================

> **Release:** `0.1.0-beta6` &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md)
> **RFC:** RFC-005 (`OBS-02`) — OpenTelemetry SDK bridge

OpenTelemetry SDK bridge for the [Waffle Commons](https://github.com/waffle-commons) framework. It implements
`Waffle\Commons\Contracts\Telemetry\TracerInterface` on top of the audited OpenTelemetry PHP SDK and propagates
**W3C Trace Context** on outbound HTTP calls — isolating the vendor SDK in this adapter so the core packages
stay vendor-free. The SDK-free defaults, the Prometheus endpoint, and the metric collectors live in the
companion [`waffle-commons/telemetry`](https://github.com/waffle-commons/telemetry) package.

## 📦 Installation

```bash
composer require waffle-commons/telemetry-otel
```

Pulls in `open-telemetry/api` + `open-telemetry/sdk` as runtime dependencies — this package is the **only**
place in the Waffle ecosystem where the OTel SDK is allowed.

## 🧱 Surface

| Class | Role |
| :--- | :--- |
| `Waffle\Commons\TelemetryOtel\Trace\OtelTracer` | `final readonly` adapter of an OTel `TracerInterface` to the framework's `Contracts\Telemetry\TracerInterface`. Activates each started span so nested spans compose through OTel's own context stack. |
| `Waffle\Commons\TelemetryOtel\Trace\OtelSpan` | `final readonly` adapter of an OTel span (plus its active `ScopeInterface`) to `Contracts\Telemetry\SpanInterface`. `end()` detaches the scope, then ends the span. |
| `Waffle\Commons\TelemetryOtel\Trace\OtelSpanContext` | `final readonly` adapter of an OTel span context to `Contracts\Telemetry\SpanContextInterface`; renders a W3C `traceparent` string via `toTraceparent()`. |
| `Waffle\Commons\TelemetryOtel\Propagation\W3CTraceContextPropagator` | `final readonly` `TextMapPropagatorInterface` — `inject()` serialises the Waffle span context as `traceparent`/`tracestate`; `extract()` delegates to OTel's audited `TraceContextPropagator` for parsing. |
| `Waffle\Commons\TelemetryOtel\Factory\OtelTracerFactory` | Static factory assembling a ready-to-use `OtelTracer` from any `SpanExporterInterface`, or `console()` for a zero-dependency JSON exporter to `php://stdout`. |

## 🚀 Usage

```php
use Waffle\Commons\TelemetryOtel\Factory\OtelTracerFactory;
use Waffle\Commons\TelemetryOtel\Propagation\W3CTraceContextPropagator;

// Local/dev: export spans as JSON to php://stdout (docker logs becomes a zero-dependency
// span collector). Swap OtelTracerFactory::create($serviceName, $exporter) for a real
// OTLP exporter in production.
$tracer = OtelTracerFactory::console(serviceName: 'waffle-app');
$propagator = new W3CTraceContextPropagator();

$container->set(TracerInterface::class, $tracer);
```

Registered as shared services, `$tracer` and `$propagator` are consumed unmodified by
`waffle-commons/telemetry`'s `TracingMiddleware` (per-request server span + inbound `traceparent` extraction)
and by `waffle-commons/http-client`'s `Client` (outbound `traceparent` propagation) — one end-to-end trace
across both directions of a request, with zero code in either package aware that OpenTelemetry exists.

## 🔀 Span-kind mapping

`OtelTracer::startSpan()` translates the framework's `Contracts\Telemetry\Enum\SpanKind` 1:1 onto the OTel
API's `SpanKind` constants:

| `Contracts\Telemetry\Enum\SpanKind` | OTel `SpanKind` |
| :--- | :--- |
| `Internal` | `KIND_INTERNAL` |
| `Server` | `KIND_SERVER` |
| `Client` | `KIND_CLIENT` |
| `Producer` | `KIND_PRODUCER` |
| `Consumer` | `KIND_CONSUMER` |

When an explicit `$parent` span context is supplied — typically one extracted from an inbound `traceparent`
by `W3CTraceContextPropagator::extract()` — the new span continues that remote trace via
`OtelApiSpanContext::createFromRemoteParent()`; otherwise it parents off OTel's own active context, so nested
`startSpan()` calls compose naturally without threading a context object through every call site.

## 🐘 PHP 8.5 features used

- `final readonly class` on every adapter (`OtelTracer`, `OtelSpan`, `OtelSpanContext`,
  `W3CTraceContextPropagator`) — each wraps an injected SDK object and mutates nothing of its own.
- `#[\Override]` on every interface implementation.
- `match` expressions for the `SpanKind` → OTel-kind and `SpanStatus` → OTel-status translations.
- Static factory methods (`OtelTracerFactory::create()` / `::console()`) instead of a constructor, since the
  class assembles a `TracerProvider` rather than holding state.

## 🧭 Perimeter

The **only** Waffle package permitted to require the OpenTelemetry SDK (`open-telemetry/*`) — `mago guard`'s
`[guard.perimeter]` in [`mago.toml`](./mago.toml) allows the `OpenTelemetry\**` namespace here and nowhere
else in the ecosystem. Otherwise depends only on `waffle-commons/contracts`; production code under
`Waffle\Commons\TelemetryOtel` may depend only on itself, `Contracts\**`, the OTel SDK, `Psr\**`, and PHP core
/ `Psl\**`. Test code under `WaffleTests\Commons\TelemetryOtel` is unrestricted (`@all`).

Contract-first, component-agnostic by construction: components compose through `waffle-commons/contracts`,
never directly through one another.

## 🧵 Worker safety

Every adapter (`OtelTracer`, `OtelSpan`, `OtelSpanContext`, `W3CTraceContextPropagator`) is `final readonly`
and holds no per-request mutable state of its own — the OpenTelemetry SDK owns the active-context stack
internally, and a `SimpleSpanProcessor` exports each span synchronously on `end()` so nothing is buffered
across requests waiting on a background flush. There is nothing here for a FrankenPHP worker to leak between
requests (`wfl igor` 0 KO).

## 🧪 Testing

```bash
docker exec -w /waffle-commons/telemetry-otel waffle-dev composer tests
```

## 📚 Documentation

Central framework docs (Diátaxis) for this component:

- Reference: [`reference/telemetry-otel.md`](https://github.com/waffle-commons/documentation/blob/main/reference/telemetry-otel.md)
- Explanation: [`explanation/observability-telemetry.md`](https://github.com/waffle-commons/documentation/blob/main/explanation/observability-telemetry.md)
- Full documentation tree: [waffle-commons/documentation](https://github.com/waffle-commons/documentation)

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).

