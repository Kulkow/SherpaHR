<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use ProjectDesk\Domain\Exception\DomainException;

final readonly class TaskTitle
{
    public const int MAX_BYTES = 200;

    public string $value;

    public function __construct(string $value)
    {
        $title = trim($value);
        if ($title === '' || strlen($value) > self::MAX_BYTES || preg_match('//u', $value) !== 1) {
            throw new DomainException('Название задачи должно содержать от 1 до 200 байт UTF-8.');
        }

        $this->value = $title;
    }
}
