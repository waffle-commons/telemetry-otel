# Changelog — waffle-commons/telemetry-otel

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta7] — 2026-11

### Changed
- Lockstep version bump with the Beta-7 wave. No behavioural changes in this component since `0.1.0-beta6`. Development only: the toolchain moved to mago 1.50, psalm 6.19 and igor-php 0.9.7, and `phpunit.xml` now validates against the installed PHPUnit schema.
- igor-php 0.9.7's new findings on `OtelSpan::setAttribute()`, `OtelSpan::setStatus()` and `OtelTracer::startSpan()` are adjudicated with a method-level `#[WorkerSafe]` stating why nothing survives the request; the `mago guard` perimeter permits `IgorPhp\IgorBundle\Attribute\**` (development metadata). No behavioural change.

## [0.1.0-beta6] — 2026-09

**Theme: documentation.**

### Fixed
- README corrected: no class here implements `ResettableInterface`; the adapters are stateless `final readonly` wrappers over the OTel SDK's own context stack.

### Documentation
- The README now links into the central Diátaxis documentation tree (DOC-02).

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
