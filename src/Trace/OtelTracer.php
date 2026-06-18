<?php

declare(strict_types=1);

namespace Waffle\Commons\TelemetryOtel\Trace;

use OpenTelemetry\API\Trace\Span as OtelSpanFacade;
use OpenTelemetry\API\Trace\SpanContext as OtelApiSpanContext;
use OpenTelemetry\API\Trace\SpanKind as OtelSpanKind;
use OpenTelemetry\API\Trace\TracerInterface as OtelTracerInterface;
use OpenTelemetry\API\Trace\TraceState;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\SpanContextInterface;
use Waffle\Commons\Contracts\Telemetry\SpanInterface;
use Waffle\Commons\Contracts\Telemetry\TracerInterface;

/**
 * Adapts an OpenTelemetry tracer to the Waffle {@see TracerInterface}. Each started
 * span is activated, so nested spans and {@see self::currentContext()} compose
 * through OTel's context. When an explicit `$parent` span context is supplied (e.g.
 * one extracted from an inbound W3C `traceparent`), the new span continues that
 * remote trace; otherwise it parents off OTel's active context.
 *
 * Stateless: OTel owns the active-context stack, so this wrapper carries no
 * per-request state and is safe across resident-worker requests.
 */
final readonly class OtelTracer implements TracerInterface
{
    public function __construct(
        private OtelTracerInterface $tracer,
    ) {}

    #[\Override]
    public function startSpan(
        string $name,
        SpanKind $kind = SpanKind::Internal,
        ?SpanContextInterface $parent = null,
    ): SpanInterface {
        $builder = $this->tracer
            ->spanBuilder($name === '' ? 'span' : $name)
            ->setSpanKind(match ($kind) {
                SpanKind::Internal => OtelSpanKind::KIND_INTERNAL,
                SpanKind::Server => OtelSpanKind::KIND_SERVER,
                SpanKind::Client => OtelSpanKind::KIND_CLIENT,
                SpanKind::Producer => OtelSpanKind::KIND_PRODUCER,
                SpanKind::Consumer => OtelSpanKind::KIND_CONSUMER,
            });

        if ($parent !== null) {
            $builder = $builder->setParent($this->remoteParentContext($parent));
        }

        $span = $builder->startSpan();

        return new OtelSpan($span, $span->activate());
    }

    /**
     * Rebuilds an OTel parent context from a contract span context (e.g. one extracted from an
     * inbound W3C `traceparent`), so the started span continues that remote trace rather than
     * opening a fresh one. Works for any {@see SpanContextInterface} via its ids and flags.
     */
    private function remoteParentContext(SpanContextInterface $parent): ContextInterface
    {
        $traceState = $parent->traceState();

        $remote = OtelApiSpanContext::createFromRemoteParent(
            $parent->traceId(),
            $parent->spanId(),
            $parent->traceFlags(),
            $traceState === '' ? null : new TraceState($traceState),
        );

        return Context::getRoot()->withContextValue(OtelSpanFacade::wrap($remote));
    }

    #[\Override]
    public function currentContext(): ?SpanContextInterface
    {
        $context = OtelSpanFacade::getCurrent()->getContext();

        return $context->isValid() ? new OtelSpanContext($context) : null;
    }
}
