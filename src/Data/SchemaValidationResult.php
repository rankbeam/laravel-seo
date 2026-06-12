<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Data;

/**
 * Result of schema validation.
 *
 * Contains validation status, errors, warnings, and the
 * sanitized schema.
 *
 * ## Usage
 * ```php
 * $result = $validator->validate($schema);
 *
 * if ($result->isValid) {
 *     // Use $result->schema (sanitized)
 * } else {
 *     foreach ($result->errors as $error) {
 *         echo "{$error['field']}: {$error['message']}";
 *     }
 * }
 * ```
 */
readonly class SchemaValidationResult
{
    /**
     * Create a new validation result.
     *
     * @param bool $isValid Whether the schema is valid
     * @param array<int, array{field: string, message: string}> $errors Validation errors
     * @param array<int, array{field: string, message: string}> $warnings Validation warnings
     * @param array<string, mixed> $schema The validated/sanitized schema
     */
    public function __construct(
        public bool $isValid,
        public array $errors = [],
        public array $warnings = [],
        public array $schema = [],
    ) {}

    /**
     * Create a valid result.
     *
     * @param array<string, mixed> $schema
     * @param array<int, array{field: string, message: string}> $warnings
     */
    public static function valid(array $schema, array $warnings = []): self
    {
        return new self(
            isValid: true,
            errors: [],
            warnings: $warnings,
            schema: $schema,
        );
    }

    /**
     * Create an invalid result.
     *
     * @param array<int, array{field: string, message: string}> $errors
     * @param array<int, array{field: string, message: string}> $warnings
     * @param array<string, mixed> $schema
     */
    public static function invalid(array $errors, array $warnings = [], array $schema = []): self
    {
        return new self(
            isValid: false,
            errors: $errors,
            warnings: $warnings,
            schema: $schema,
        );
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get error messages as a flat array.
     *
     * @return array<int, string>
     */
    public function getErrorMessages(): array
    {
        return array_map(
            fn ($error) => $error['message'],
            $this->errors
        );
    }

    /**
     * Get warning messages as a flat array.
     *
     * @return array<int, string>
     */
    public function getWarningMessages(): array
    {
        return array_map(
            fn ($warning) => $warning['message'],
            $this->warnings
        );
    }

    /**
     * Get errors grouped by field.
     *
     * @return array<string, array<int, string>>
     */
    public function getErrorsByField(): array
    {
        $grouped = [];

        foreach ($this->errors as $error) {
            $grouped[$error['field']][] = $error['message'];
        }

        return $grouped;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'isValid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'schema' => $this->schema,
        ];
    }
}
