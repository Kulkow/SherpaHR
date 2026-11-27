<?php

declare(strict_types=1);

namespace ProjectDesk\Application;

use RuntimeException;

final class UseCaseException extends RuntimeException
{
    public const int NotFound = 1;
    public const int Conflict = 2;
    public const int Forbidden = 3;
}
