<?php

declare(strict_types=1);

namespace WaffleTests\Commons\TelemetryOtel\Trace;

use OpenTelemetry\API\Trace\SpanKind as OtelSpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanStatus;
use Waffle\Commons\TelemetryOtel\Propagation\W3CTraceContextPropagator;
use Waffle\Commons\TelemetryOtel\Trace\OtelSpan;
use Waffle\Commons\TelemetryOtel\Trace\OtelSpanContext;
use Waffle\Commons\TelemetryOtel\Trace\OtelTracer;
use WaffleTests\Commons\TelemetryOtel\AbstractTestCase;

#[CoversClass(OtelTracer::class)]
#[CoversClass(OtelSpan::class)]
#[CoversClass(OtelSpanContext::class)]
final class OtelTracerTest extends AbstractTestCase
{
    private InMemoryExporter $exporter;
    private OtelTracer $tracer;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new InMemoryExporter();
        $provider = new TracerProvider(new SimpleSpanProcessor($this->exporter));
        $this->tracer = new OtelTracer($provider->getTracer('test'));
    }

    public function testStartSpanExportsNameKindAttributesAndStatus(): void
    {
        $span = $this->tracer->startSpan('waffle.routing', SpanKind::Server);
        $span->setAttribute('http.route', '/users/{id}');
        $span->setAttribute('', 'ignored');
        $span->setStatus(SpanStatus::Ok);
        $span->end();

        $exported = $this->exporter->getSpans();
        static::assertCount(1, $exported);
        static::assertSame('waffle.routing', $exported[0]->getName());
        static::assertSame(OtelSpanKind::KIND_SERVER, $exported[0]->getKind());
        static::assertSame('/users/{id}', $exported[0]->getAttributes()->get('http.route'));
        static::assertNull($exported[0]->getAttributes()->get(''));
        static::assertSame(StatusCode::STATUS_OK, $exported[0]->getStatus()->getCode());
    }

    public function testRecordExceptionMarksErrorStatus(): void
    {
        $span = $this->tracer->startSpan('waffle.db.query', SpanKind::Client);
        $span->recordException(new RuntimeException('boom'));
        $span->setStatus(SpanStatus::Error);
        $span->end();

        static::assertSame(StatusCode::STATUS_ERROR, $this->exporter->getSpans()[0]->getStatus()->getCode());
    }

    public function testCurrentContextReflectsTheActiveSpan(): void
    {
        static::assertNull($this->tracer->currentContext());

        $span = $this->tracer->startSpan('active');
        $context = $this->tracer->currentContext();

        static::assertInstanceOf(OtelSpanContext::class, $context);
        static::assertTrue($context->isValid());
        static::assertSame($span->context()->traceId(), $context->traceId());
        static::assertMatchesRegularExpression(
            '/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/',
            $context->toTraceparent(),
        );

        $span->end();
        static::assertNull($this->tracer->currentContext());
    }

    public function testMapsEverySpanKind(): void
    {
        foreach ([
            SpanKind::Internal,
            SpanKind::Server,
            SpanKind::Client,
            SpanKind::Producer,
            SpanKind::Consumer,
        ] as $kind) {
            $this->tracer->startSpan('k', $kind)->end();
        }

        static::assertCount(5, $this->exporter->getSpans());
    }

    public function testSetStatusUnsetIsMapped(): void
    {
        $span = $this->tracer->startSpan('x');
        $span->setStatus(SpanStatus::Unset);
        $span->end();

        static::assertSame(StatusCode::STATUS_UNSET, $this->exporter->getSpans()[0]->getStatus()->getCode());
    }

    /**
     * A span started with an explicit remote parent (as extracted from an inbound W3C
     * `traceparent`) must CONTINUE that trace — proving the downstream half of distributed
     * tracing. Exercises both `tracestate` branches of the remote-parent rebuild.
     */
    public function testStartSpanContinuesAnExplicitRemoteParent(): void
    {
        $propagator = new W3CTraceContextPropagator();
        $withState = $propagator->extract([
            'traceparent' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01',
            'tracestate' => 'vendor=value',
        ]);
        $withoutState = $propagator->extract([
            'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        ]);
        static::assertNotNull($withState);
        static::assertNotNull($withoutState);

        $this->tracer->startSpan('downstream-a', SpanKind::Server, $withState)->end();
        $this->tracer->startSpan('downstream-b', SpanKind::Server, $withoutState)->end();

        $exported = $this->exporter->getSpans();
        static::assertCount(2, $exported);
        // Each child span inherits its remote parent's trace id rather than minting a fresh one.
        static::assertSame('0af7651916cd43dd8448eb211c80319c', $exported[0]->getContext()->getTraceId());
        static::assertSame('b7ad6b7169203331', $exported[0]->getParentSpanId());
        static::assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $exported[1]->getContext()->getTraceId());
    }
}
