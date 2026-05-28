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
        private readonly array $previewRows = [],
        string $message = 'Bulk upload validation failed.'
    ) {
        if (
            $message === 'Bulk upload validation failed.'
            && isset($errors[0]['message'])
            && is_string($errors[0]['message'])
            && trim($errors[0]['message']) !== ''
        ) {
            $message = $errors[0]['message'];
        }

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(): array
    {
        return $this->previewRows;
    }
}
