<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\User;

use ProjectDesk\Domain\Exception\DomainException;

final readonly class UserId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new DomainException('Идентификатор пользователя должен быть положительным.');
        }
    }
}
