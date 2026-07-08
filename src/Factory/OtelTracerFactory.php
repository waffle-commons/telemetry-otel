<?php

declare(strict_types=1);

namespace Waffle\Commons\TelemetryOtel\Factory;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Waffle\Commons\TelemetryOtel\Trace\OtelTracer;

/**
 * Assembles a ready-to-use {@see OtelTracer} from the OpenTelemetry SDK, keeping every
 * `OpenTelemetry\SDK\*` reference inside this perimeter (the framework core and demo apps
 * only ever see the contract {@see \Waffle\Commons\Contracts\Telemetry\TracerInterface}).
 *
 * A `SimpleSpanProcessor` is used on purpose: it exports each span synchronously on `end()`,
 * which keeps a FrankenPHP worker's stdout deterministic (no background batch flush to lose).
 */
final class OtelTracerFactory
{
    /**
     * Build a tracer that tags every span with `service.name = $serviceName` and exports through
     * the supplied span exporter (e.g. an OTLP exporter in production, an in-memory one in tests).
     */
    public static function create(string $serviceName, SpanExporterInterface $exporter): OtelTracer
    {
        $resource = ResourceInfo::create(Attributes::create(['service.name' => $serviceName]));
        $provider = new TracerProvider(new SimpleSpanProcessor($exporter), null, $resource);

        return new OtelTracer($provider->getTracer('waffle-commons/telemetry-otel'));
    }

    /**
     * Convenience for local/dev wiring: export spans as JSON to `php://stdout`, so a container's
     * logs become a zero-dependency span collector (`docker logs <service>`).
     */
    public static function console(string $serviceName): OtelTracer
    {
        $transport = Registry::transportFactory('stream')->create('php://stdout', 'application/json');

        return self::create($serviceName, new ConsoleSpanExporter($transport));
    }
}
