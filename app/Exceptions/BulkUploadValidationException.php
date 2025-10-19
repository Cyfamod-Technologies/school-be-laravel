<?php

namespace App\Exceptions;

use Exception;

class BulkUploadValidationException extends Exception
{
    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    public function __construct(
        private readonly array $errors,
        private readonly ?string $errorCsv = null,
        string $message = 'Bulk upload validation failed.'
    ) {
        parent::__construct($message, 422);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function errorCsv(): ?string
    {
        return $this->errorCsv;
    }
}
