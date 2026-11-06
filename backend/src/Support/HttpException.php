<?php

declare(strict_types=1);

namespace DesignLila\Support;

use RuntimeException;

final class HttpException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        string $message,
        private readonly int $status = 400,
        private readonly array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }
}
