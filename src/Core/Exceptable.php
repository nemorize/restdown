<?php

namespace App\Core;

use RuntimeException;

class Exceptable extends RuntimeException
{
    private array|object $payload;

    /**
     * Constructor.
     *
     * @param string $message
     * @param int $code
     * @param array|object $payload
     */
    public function __construct (string $message = '', int $code = 400, array|object $payload = [])
    {
        parent::__construct($message, $code);
        $this->payload = $payload;
    }

    /**
     * Get payload.
     *
     * @return array
     */
    public function getPayload (): array
    {
        return (array) ($this->payload ?? []);
    }
}