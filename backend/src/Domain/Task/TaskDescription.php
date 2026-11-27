<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use ProjectDesk\Domain\Exception\DomainException;

final readonly class TaskDescription
{
    public const int MAX_BYTES = 10000;

    public string $value;

    public function __construct(string $value)
    {
        if (strlen($value) > self::MAX_BYTES || preg_match('//u', $value) !== 1) {
            throw new DomainException('Описание задачи должно содержать не более 10000 байт UTF-8.');
        }

        $this->value = trim($value);
    }
}
