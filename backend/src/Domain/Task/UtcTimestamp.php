<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use DateTimeImmutable;
use DateTimeZone;
use ProjectDesk\Domain\Exception\DomainException;

final readonly class UtcTimestamp
{
    private const string FORMAT = 'Y-m-d\TH:i:s\Z';

    public function __construct(public string $value)
    {
        if (strlen($value) !== 20 || str_contains($value, "\0")) {
            throw new DomainException('Ожидается дата в UTC в формате YYYY-MM-DDTHH:mm:ssZ.');
        }

        $date = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format(self::FORMAT) !== $value) {
            throw new DomainException('Ожидается дата в UTC в формате YYYY-MM-DDTHH:mm:ssZ.');
        }
    }
}
