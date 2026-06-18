<?php

declare(strict_types=1);

namespace Waffle\Commons\TelemetryOtel\Trace;

use OpenTelemetry\API\Trace\SpanInterface as OtelSpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanStatus;
use Waffle\Commons\Contracts\Telemetry\SpanContextInterface;
use Waffle\Commons\Contracts\Telemetry\SpanInterface;

/**
 * Adapts an OpenTelemetry span (plus its active scope) to the Waffle
 * {@see SpanInterface}. {@see self::end()} detaches the scope, then ends the span.
 */
final readonly class OtelSpan implements SpanInterface
{
    public function __construct(
        private OtelSpanInterface $span,
        private ScopeInterface $scope,
    ) {}

    #[\Override]
    public function setAttribute(string $key, string|int|float|bool $value): void
    {
        if ($key !== '') {
            $this->span->setAttribute($key, $value);
        }
    }

    #[\Override]
    public function recordException(Throwable $exception): void
    {
        $this->span->recordException($exception);
    }

    #[\Override]
    public function setStatus(SpanStatus $status): void
    {
        $this->span->setStatus(match ($status) {
            SpanStatus::Ok => StatusCode::STATUS_OK,
            SpanStatus::Error => StatusCode::STATUS_ERROR,
            SpanStatus::Unset => StatusCode::STATUS_UNSET,
        });
    }

    #[\Override]
    public function context(): SpanContextInterface
    {
        return new OtelSpanContext($this->span->getContext());
    }

    #[\Override]
    public function end(): void
    {
        $this->scope->detach();
        $this->span->end();
    }
}
