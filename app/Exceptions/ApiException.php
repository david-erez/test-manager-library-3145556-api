<?php

namespace App\Exceptions;

use Exception;

// api-contract.md §1: the single error body for every error.
class ApiException extends Exception
{
    public function __construct(
        private readonly string $apiCode,
        string $userMessage,
        private readonly int $status,
        private readonly array $details = [],
    ) {
        parent::__construct($userMessage);
    }

    public function render()
    {
        return response()->json([
            'code' => $this->apiCode,
            'message' => $this->getMessage(),
            'details' => $this->details,
        ], $this->status);
    }
}
