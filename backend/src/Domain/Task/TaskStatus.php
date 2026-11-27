<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use ProjectDesk\Domain\Exception\DomainException;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';

    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw new DomainException('Недопустимый статус задачи.');
    }

    /** @return list<self> */
    public function allowTransactions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress],
            self::InProgress => [self::Open, self::Blocked, self::Done],
            self::Blocked => [self::Open],
            self::Done => [self::Open],
        };
    }

    public function canTransaction(self $target): bool
    {
        return in_array($target, $this->allowTransactions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Открыта',
            self::InProgress => 'В работе',
            self::Blocked => 'Заблокирована',
            self::Done => 'Готово',
        };
    }
}
