<?php

declare(strict_types=1);

namespace ProjectDesk\Infrastructure\Sqlite;

use LogicException;
use ProjectDesk\Domain\Project\ProjectId;
use ProjectDesk\Domain\Task\DueAt;
use ProjectDesk\Domain\Task\Task;
use ProjectDesk\Domain\Task\TaskDescription;
use ProjectDesk\Domain\Task\TaskId;
use ProjectDesk\Domain\Task\TaskStatus;
use ProjectDesk\Domain\Task\TaskTitle;
use ProjectDesk\Domain\Task\TaskVersion;
use ProjectDesk\Domain\Task\UtcTimestamp;
use ProjectDesk\Domain\User\UserId;
use RuntimeException;

final readonly class TaskRepository
{
    private const string COLUMNS = 'id, project_id, title, description, status, assignee_id, due_at, updated_at, version';

    public function __construct(private Sqlite $db)
    {
    }

    public function find(TaskId $id): ?Task
    {
        $row = $this->db->one('SELECT ' . self::COLUMNS . ' FROM tasks WHERE id = ?', [$id->value]);
        return $row === null ? null : $this->restore($row);
    }

    public function insert(Task $task): Task
    {
        if ($task->id !== null) {
            throw new LogicException('Only a new task can be inserted.');
        }

        $row = $this->db->one(
            'INSERT INTO tasks (title, description, status, assignee_id, due_at, updated_at, version, project_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING ' . self::COLUMNS,
            [...$this->values($task), $task->projectId->value],
        ) ?? throw new RuntimeException('SQLite did not return the inserted task.');

        return $this->restore($row);
    }

    public function update(Task $task, TaskVersion $expectedVersion): ?Task
    {
        $id = $task->id ?? throw new LogicException('Only a saved task can be updated.');
        $row = $this->db->one(
            'UPDATE tasks SET title = ?, description = ?, status = ?, assignee_id = ?, due_at = ?,
                updated_at = ?, version = ?
             WHERE id = ? AND project_id = ? AND version = ? RETURNING ' . self::COLUMNS,
            [...$this->values($task), $id->value, $task->projectId->value, $expectedVersion->value],
        );

        return $row === null ? null : $this->restore($row);
    }

    /** @return list<int|string|null> */
    private function values(Task $task): array
    {
        return [
            $task->title->value,
            $task->description->value,
            $task->status->value,
            $task->assigneeId?->value,
            $task->dueAt?->timestamp->value,
            $task->updatedAt->value,
            $task->version->value,
        ];
    }

    /** @param array<string, mixed> $row */
    private function restore(array $row): Task
    {
        return Task::restore(
            new TaskId(Sqlite::integer($row, 'id')),
            new ProjectId(Sqlite::integer($row, 'project_id')),
            new TaskTitle(Sqlite::text($row, 'title')),
            new TaskDescription(Sqlite::text($row, 'description')),
            TaskStatus::fromCode(Sqlite::text($row, 'status')),
            ($row['assignee_id'] ?? null) === null ? null : new UserId(Sqlite::integer($row, 'assignee_id')),
            ($row['due_at'] ?? null) === null ? null : new DueAt(new UtcTimestamp(Sqlite::text($row, 'due_at'))),
            new UtcTimestamp(Sqlite::text($row, 'updated_at')),
            new TaskVersion(Sqlite::integer($row, 'version')),
        );
    }
}
