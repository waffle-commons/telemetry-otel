[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/telemetry-otel/require/php)](https://packagist.org/packages/waffle-commons/telemetry-otel)
[![PHP CI](https://github.com/waffle-commons/telemetry-otel/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/telemetry-otel/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/telemetry-otel/graph/badge.svg)](https://codecov.io/gh/waffle-commons/telemetry-otel)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/telemetry-otel/v)](https://packagist.org/packages/waffle-commons/telemetry-otel)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/telemetry-otel)](https://github.com/waffle-commons/telemetry-otel/blob/main/LICENSE.md)

# Waffle Commons — Telemetry (OpenTelemetry Bridge)

OpenTelemetry SDK bridge for the [Waffle Commons](https://github.com/waffle-commons) framework. It implements
`Waffle\Commons\Contracts\Telemetry\TracerInterface` on top of the audited OpenTelemetry PHP SDK and propagates
**W3C Trace Context** on outbound HTTP calls — isolating the vendor SDK in this adapter so the core packages
stay vendor-free.

> **Status:** scaffolding for **Beta 5 / AXE 5 (RFC-005)** — no implementation yet. The SDK-free defaults, the
> Prometheus endpoint and the metric collectors live in
> [`waffle-commons/telemetry`](https://github.com/waffle-commons/telemetry).

## Perimeter

The **only** Waffle package permitted to require the OpenTelemetry SDK (`open-telemetry/*`). Depends on
`waffle-commons/contracts` plus the OTel SDK; `mago guard` is configured to allow the SDK here and nowhere
else. The request-scoped trace-context holder implements `ResettableInterface` (`wfl igor` 0 KO).

## Development

```shell
composer install
composer mago     # fmt + lint + analyze + guard — must be ZERO output
composer tests    # PHPUnit 12.5, >=95% coverage
composer igor     # worker-safety audit — 0 KO
```

## License

MIT — see [LICENSE.md](./LICENSE.md).
