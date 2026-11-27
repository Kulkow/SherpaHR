<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use ProjectDesk\Domain\Exception\DomainException;

final readonly class CommentId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new DomainException('Идентификатор комментария должен быть положительным.');
        }
    }
}
