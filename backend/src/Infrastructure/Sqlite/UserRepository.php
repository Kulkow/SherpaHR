<?php

declare(strict_types=1);

namespace ProjectDesk\Infrastructure\Sqlite;

use ProjectDesk\Domain\User\User;
use ProjectDesk\Domain\User\UserId;

final readonly class UserRepository
{
    public function __construct(private Sqlite $db)
    {
    }

    public function find(UserId $id): ?User
    {
        $row = $this->db->one('SELECT id, name FROM users WHERE id = ?', [$id->value]);
        return $row === null ? null : new User($id, Sqlite::text($row, 'name'));
    }
}
