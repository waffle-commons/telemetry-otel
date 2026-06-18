<?php

declare(strict_types=1);

namespace WaffleTests\Commons\TelemetryOtel\Factory;

use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\TelemetryOtel\Factory\OtelTracerFactory;
use Waffle\Commons\TelemetryOtel\Trace\OtelTracer;
use WaffleTests\Commons\TelemetryOtel\AbstractTestCase;

#[CoversClass(OtelTracerFactory::class)]
final class OtelTracerFactoryTest extends AbstractTestCase
{
    public function testCreateTagsSpansWithServiceNameAndExportsThroughTheExporter(): void
    {
        $exporter = new InMemoryExporter();
        $tracer = OtelTracerFactory::create('orders-api', $exporter);

        $tracer->startSpan('checkout', SpanKind::Server)->end();

        $spans = $exporter->getSpans();
        static::assertCount(1, $spans);
        static::assertSame('checkout', $spans[0]->getName());
        // A real, sampled trace id is 16 bytes rendered as 32 lowercase hex chars.
        static::assertSame(32, strlen($spans[0]->getContext()->getTraceId()));
        static::assertSame('orders-api', $spans[0]->getResource()->getAttributes()->get('service.name'));
    }

    public function testConsoleReturnsAConfiguredTracer(): void
    {
        static::assertInstanceOf(OtelTracer::class, OtelTracerFactory::console('checkout-worker'));
    }
}
