<?php

declare(strict_types=1);

namespace Waffle\Commons\TelemetryOtel\Trace;

use OpenTelemetry\API\Trace\SpanContextInterface as OtelSpanContextInterface;
use Waffle\Commons\Contracts\Telemetry\SpanContextInterface;

use function sprintf;

/**
 * Adapts an OpenTelemetry span context to the Waffle {@see SpanContextInterface}.
 */
final readonly class OtelSpanContext implements SpanContextInterface
{
    public function __construct(
        private OtelSpanContextInterface $context,
    ) {}

    #[\Override]
    public function traceId(): string
    {
        return $this->context->getTraceId();
    }

    #[\Override]
    public function spanId(): string
    {
        return $this->context->getSpanId();
    }

    #[\Override]
    public function traceFlags(): int
    {
        return $this->context->getTraceFlags();
    }

    #[\Override]
    public function traceState(): string
    {
        $state = $this->context->getTraceState();

        return $state === null ? '' : (string) $state;
    }

    #[\Override]
    public function isValid(): bool
    {
        return $this->context->isValid();
    }

    #[\Override]
    public function toTraceparent(): string
    {
        return sprintf(
            '00-%s-%s-%02x',
            $this->context->getTraceId(),
            $this->context->getSpanId(),
            $this->context->getTraceFlags(),
        );
    }
}
