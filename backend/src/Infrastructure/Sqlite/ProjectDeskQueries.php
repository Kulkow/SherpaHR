<?php

declare(strict_types=1);

namespace ProjectDesk\Infrastructure\Sqlite;

use ProjectDesk\Domain\Project\ProjectId;
use ProjectDesk\Domain\Task\TaskId;
use ProjectDesk\Domain\User\UserId;
use RuntimeException;

/**
 * @phpstan-type Row array<string, mixed>
 */
final readonly class ProjectDeskQueries
{
    public function __construct(private Sqlite $db)
    {
    }

    /** @return list<Row> */
    public function users(): array
    {
        return $this->db->all('SELECT id, name FROM users ORDER BY id');
    }

    public function seededAt(): string
    {
        $row = $this->db->one('SELECT value FROM metadata WHERE key = ?', ['seeded_at'])
            ?? throw new RuntimeException('Missing SQLite seeded_at metadata.');
        return Sqlite::text($row, 'value');
    }

    /** @return list<Row> */
    public function projects(UserId $userId): array
    {
        $projects = $this->db->all(
            'SELECT p.id, p.name, p.description, p.owner_id, p.archived,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count
             FROM projects p
             JOIN project_members m ON m.project_id = p.id
             WHERE m.user_id = ?
             ORDER BY p.archived, p.id',
            [$userId->value],
        );
        $members = $this->db->all(
            'SELECT m.project_id, u.id, u.name
             FROM project_members m JOIN users u ON u.id = m.user_id
             JOIN project_members visible ON visible.project_id = m.project_id
             WHERE visible.user_id = ?
             ORDER BY m.project_id, u.id',
            [$userId->value],
        );

        $membersByProject = [];
        foreach ($members as $member) {
            $membersByProject[Sqlite::integer($member, 'project_id')][] = [
                'id' => Sqlite::integer($member, 'id'),
                'name' => Sqlite::text($member, 'name'),
            ];
        }

        foreach ($projects as $index => $project) {
            $projects[$index]['archived'] = Sqlite::integer($project, 'archived') !== 0;
            $projects[$index]['members'] = $membersByProject[Sqlite::integer($project, 'id')] ?? [];
        }
        return $projects;
    }

    /** @return list<Row> */
    public function activeOwnedTasks(UserId $userId): array
    {
        return $this->db->all(
            'SELECT t.id, t.project_id, p.name AS project_name, t.title, t.status, t.assignee_id, t.due_at,
                    EXISTS(SELECT 1 FROM comments c WHERE c.task_id = t.id AND c.attention = \'requested\') AS marked
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             JOIN project_members m ON m.project_id = p.id AND m.user_id = p.owner_id
             WHERE p.owner_id = ? AND p.archived = 0
             ORDER BY t.id',
            [$userId->value],
        );
    }

    /** @return list<Row> */
    public function tasks(ProjectId $projectId): array
    {
        return $this->db->all(
            'SELECT id, project_id, title, description, status, assignee_id, due_at, updated_at, version
             FROM tasks WHERE project_id = ? ORDER BY id',
            [$projectId->value],
        );
    }

    /** @return list<Row> */
    public function comments(TaskId $taskId): array
    {
        return $this->db->all(
            'SELECT c.id, c.task_id, c.user_id, c.body, c.created_at, c.attention, u.name AS author
             FROM comments c JOIN users u ON u.id = c.user_id WHERE c.task_id = ? ORDER BY c.id',
            [$taskId->value],
        );
    }
}
