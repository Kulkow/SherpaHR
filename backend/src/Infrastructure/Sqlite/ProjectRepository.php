<?php

declare(strict_types=1);

namespace ProjectDesk\Infrastructure\Sqlite;

use ProjectDesk\Domain\Project\Project;
use ProjectDesk\Domain\Project\ProjectId;
use ProjectDesk\Domain\Project\ProjectMember;
use ProjectDesk\Domain\User\UserId;

final readonly class ProjectRepository
{
    public function __construct(private Sqlite $db)
    {
    }

    public function find(ProjectId $id): ?Project
    {
        $row = $this->db->one(
            'SELECT name, description, owner_id, archived FROM projects WHERE id = ?',
            [$id->value],
        );
        if ($row === null) {
            return null;
        }

        $members = $this->db->all(
            'SELECT user_id FROM project_members WHERE project_id = ? ORDER BY user_id',
            [$id->value],
        );

        return new Project(
            $id,
            Sqlite::text($row, 'name'),
            Sqlite::text($row, 'description'),
            new UserId(Sqlite::integer($row, 'owner_id')),
            Sqlite::integer($row, 'archived') !== 0,
            array_map(
                static fn (array $member): ProjectMember =>
                    new ProjectMember($id, new UserId(Sqlite::integer($member, 'user_id'))),
                $members,
            ),
        );
    }
}
