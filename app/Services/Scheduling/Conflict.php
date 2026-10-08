<?php

namespace App\Services\Scheduling;

use JsonSerializable;

/**
 * A single problem found while validating a proposed schedule.
 * Errors block saving; warnings can be acknowledged and overridden.
 */
final class Conflict implements JsonSerializable
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    private function __construct(
        public readonly string $severity,
        public readonly string $rule,
        public readonly string $message,
        public readonly ?int $relatedScheduleId = null,
    ) {
    }

    public static function error(string $rule, string $message, ?int $relatedScheduleId = null): self
    {
        return new self(self::ERROR, $rule, $message, $relatedScheduleId);
    }

    public static function warning(string $rule, string $message, ?int $relatedScheduleId = null): self
    {
        return new self(self::WARNING, $rule, $message, $relatedScheduleId);
    }

    public function isError(): bool
    {
        return $this->severity === self::ERROR;
    }

    public function jsonSerialize(): array
    {
        return [
            'severity' => $this->severity,
            'rule' => $this->rule,
            'message' => $this->message,
            'related_schedule_id' => $this->relatedScheduleId,
        ];
    }
}
