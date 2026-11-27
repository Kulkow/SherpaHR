<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use ProjectDesk\Domain\Exception\DomainException;

final readonly class CommentBody
{
    public const int MAX_BYTES = 5000;

    public string $value;

    public function __construct(string $value)
    {
        $body = trim($value);
        if ($body === '' || strlen($value) > self::MAX_BYTES || preg_match('//u', $value) !== 1) {
            throw new DomainException('Комментарий должен содержать от 1 до 5000 байт UTF-8.');
        }

        $this->value = $body;
    }
}
