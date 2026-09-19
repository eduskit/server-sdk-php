<?php

namespace Eduskit;

class EduskitError extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $traceId = null,
        public readonly ?string $path = null,
        public readonly ?string $source = null,
    ) {
        parent::__construct($message);
    }
}
