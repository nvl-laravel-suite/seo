<?php

declare(strict_types=1);

namespace Nvl\Seo\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Nvl\Seo\Enums\SeoResponseCode;
use Nvl\Support\Contracts\RespondableException;
use Nvl\Support\Exceptions\ExceptionResponse;
use Nvl\Support\Traits\InteractsWithPackageFailure;
use RuntimeException;
use Throwable;

/**
 * @api

 * Base package exception for safe SEO domain failures.
 */
abstract class SeoException extends RuntimeException implements RespondableException, ShouldntReport
{
    use InteractsWithPackageFailure;

    /** Resolve metadata through the existing native response hooks. */
    protected function exceptionResponse(): ExceptionResponse
    {
        return new ExceptionResponse('seo', SeoResponseCode::tryFrom($this->responseCode()) ?? SeoResponseCode::OperationFailed, $this->status(), $this->publicContext());
    }

    /**
     * Create a domain failure while preserving its original cause.
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }

    /**
     * Return the stable machine-readable error code.
     */
    abstract public function responseCode(): string;

    /**
     * Return the suggested HTTP response status.
     */
    abstract protected function status(): int;

    /**
     * Return safe structured context for API consumers.
     *
     * @return array<string, mixed>
     */
    public function publicContext(): array
    {
        return [];
    }
}
