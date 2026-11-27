<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Project;

use ProjectDesk\Domain\Exception\DomainException;

final readonly class ProjectId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new DomainException('Идентификатор проекта должен быть положительным.');
        }
    }
}
