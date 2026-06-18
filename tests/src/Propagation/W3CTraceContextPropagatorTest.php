<?php

declare(strict_types=1);

namespace WaffleTests\Commons\TelemetryOtel\Propagation;

use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\NullSpanContext;
use Waffle\Commons\TelemetryOtel\Propagation\W3CTraceContextPropagator;
use Waffle\Commons\TelemetryOtel\Trace\OtelSpanContext;
use Waffle\Commons\TelemetryOtel\Trace\OtelTracer;
use WaffleTests\Commons\TelemetryOtel\AbstractTestCase;

#[CoversClass(W3CTraceContextPropagator::class)]
#[CoversClass(OtelSpanContext::class)]
final class W3CTraceContextPropagatorTest extends AbstractTestCase
{
    public function testInjectWritesTraceparentFromAValidContext(): void
    {
        $tracer = new OtelTracer(new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter()))->getTracer(
            'test',
        ));
        $span = $tracer->startSpan('outbound', SpanKind::Client);
        $context = $tracer->currentContext();
        static::assertNotNull($context);

        $carrier = [];
        new W3CTraceContextPropagator()->inject($context, $carrier);
        $span->end();

        static::assertArrayHasKey('traceparent', $carrier);
        static::assertSame($context->toTraceparent(), $carrier['traceparent']);
    }

    public function testInjectSkipsAnInvalidContext(): void
    {
        $carrier = [];
        new W3CTraceContextPropagator()->inject(new NullSpanContext(), $carrier);

        static::assertSame([], $carrier);
    }

    public function testExtractExposesTheFullContextAndReInjects(): void
    {
        $carrier = [
            'traceparent' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01',
            'tracestate' => 'vendor=value',
        ];

        $context = new W3CTraceContextPropagator()->extract($carrier);
        static::assertNotNull($context);
        static::assertTrue($context->isValid());
        static::assertSame('0af7651916cd43dd8448eb211c80319c', $context->traceId());
        static::assertSame('b7ad6b7169203331', $context->spanId());
        static::assertSame(1, $context->traceFlags());
        static::assertSame('vendor=value', $context->traceState());
        static::assertSame($carrier['traceparent'], $context->toTraceparent());

        // Re-injecting the extracted context round-trips both traceparent + tracestate.
        $out = [];
        new W3CTraceContextPropagator()->inject($context, $out);
        static::assertSame($carrier['traceparent'], $out['traceparent']);
        static::assertSame('vendor=value', $out['tracestate']);
    }

    public function testExtractReturnsNullWhenTraceparentIsAbsent(): void
    {
        static::assertNull(new W3CTraceContextPropagator()->extract([]));
    }
}
