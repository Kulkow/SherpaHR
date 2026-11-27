<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use ProjectDesk\Domain\Exception\DomainException;

final readonly class TaskVersion
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new DomainException('Версия задачи должна быть положительной.');
        }
    }

    public function next(): self
    {
        if ($this->value === PHP_INT_MAX) {
            throw new DomainException('Достигнута максимальная версия задачи.');
        }

        return new self($this->value + 1);
    }
}
