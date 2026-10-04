<?php

namespace App\Modules\CommerceIntegration\Services;

use Exception;

class InsufficientStockException extends Exception
{
    /**
     * @param string $message
     * @param array<array{item_id: int, item_name: string, requested_qty: int, available_qty: int}> $failures
     */
    public function __construct(string $message, protected array $failures = [])
    {
        parent::__construct($message, 422);
    }

    public function getFailures(): array
    {
        return $this->failures;
    }
}
