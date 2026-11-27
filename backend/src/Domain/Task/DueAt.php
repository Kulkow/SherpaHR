<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

final readonly class DueAt
{
    public function __construct(public UtcTimestamp $timestamp)
    {
    }
}
