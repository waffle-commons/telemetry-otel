<?php

declare(strict_types=1);

namespace Waffle\Commons\TelemetryOtel\Propagation;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span as OtelSpanFacade;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use Waffle\Commons\Contracts\Telemetry\SpanContextInterface;
use Waffle\Commons\Contracts\Telemetry\TextMapPropagatorInterface;
use Waffle\Commons\TelemetryOtel\Trace\OtelSpanContext;

/**
 * W3C Trace Context propagator. `inject()` serialises the Waffle context via its
 * own `traceparent`; `extract()` delegates to OTel's audited TraceContextPropagator
 * for robust parsing and validation.
 */
final readonly class W3CTraceContextPropagator implements TextMapPropagatorInterface
{
    /**
     * @param array<string, string> $carrier
     * @param-out array<string, string> $carrier
     */
    #[\Override]
    public function inject(SpanContextInterface $context, array &$carrier): void
    {
        if (!$context->isValid()) {
            return;
        }

        $carrier['traceparent'] = $context->toTraceparent();
        $state = $context->traceState();
        if ($state !== '') {
            $carrier['tracestate'] = $state;
        }
    }

    /**
     * @param array<string, string> $carrier
     */
    #[\Override]
    public function extract(array $carrier): ?SpanContextInterface
    {
        $otelContext = TraceContextPropagator::getInstance()->extract($carrier, ArrayAccessGetterSetter::getInstance());
        $spanContext = OtelSpanFacade::fromContext($otelContext)->getContext();

        return $spanContext->isValid() ? new OtelSpanContext($spanContext) : null;
    }
}
