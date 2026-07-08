# Changelog — waffle-commons/telemetry-otel

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta5] — 2026-07-08

**Theme: OpenTelemetry SDK bridge (AXE 5 / RFC-005).**

### Added
- `Trace\OtelTracer`, `Trace\OtelSpan`, `Trace\OtelSpanContext` — adapt the OpenTelemetry PHP SDK to the
  framework `Contracts\Telemetry\TracerInterface` / `SpanInterface` / `SpanContextInterface`; the SDK owns the
  active-context stack so the adapters carry no per-request worker state (OBS-01).
- `Propagation\W3CTraceContextPropagator` — injects / extracts `traceparent` + `tracestate` for distributed
  tracing across services (OBS-01).
- `Factory\OtelTracerFactory` — assembles a tracer from any `SpanExporterInterface` (plus a console JSON
  exporter for local inspection), isolating every `OpenTelemetry\SDK\*` symbol in this adapter.

### Notes
- The **only** Waffle package permitted to require `open-telemetry/*`; `mago guard` allows the SDK here and
  nowhere else.
