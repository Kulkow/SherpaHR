<?php

declare(strict_types=1);

namespace ProjectDesk\Application;

use ProjectDesk\Domain\Task\AttentionReason;
use ProjectDesk\Domain\Task\TaskStatus;
use ProjectDesk\Domain\Task\UtcTimestamp;
use ProjectDesk\Domain\User\User;
use ProjectDesk\Infrastructure\Sqlite\ProjectDeskQueries;
use ProjectDesk\Infrastructure\Sqlite\Sqlite;

/**
 * @phpstan-type AttentionItem array{
 *     id: int, project_id: int, project_name: string, title: string, status: string,
 *     assignee_id: int|null, due_at: string|null, reasons: non-empty-list<string>
 * }
 */
final readonly class AttentionOverview
{
    public function __construct(private ProjectDeskQueries $queries)
    {
    }

    /** @return list<AttentionItem> */
    public function forUser(User $actor, UtcTimestamp $now): array
    {
        $items = [];
        foreach ($this->queries->activeOwnedTasks($actor->id) as $row) {
            $status = Sqlite::text($row, 'status');
            $assigneeId = ($row['assignee_id'] ?? null) === null ? null : Sqlite::integer($row, 'assignee_id');
            $dueAt = ($row['due_at'] ?? null) === null ? null : Sqlite::text($row, 'due_at');

            $reasons = [];
            if (Sqlite::integer($row, 'marked') === 1) {
                $reasons[] = AttentionReason::CommentAttention->value;
            }
            if ($status === TaskStatus::Blocked->value) {
                $reasons[] = AttentionReason::Blocked->value;
            }
            if ($status !== TaskStatus::Done->value && $dueAt !== null && $dueAt < $now->value) {
                $reasons[] = AttentionReason::Overdue->value;
            }
            if ($status !== TaskStatus::Done->value && $assigneeId === null) {
                $reasons[] = AttentionReason::Unassigned->value;
            }
            if ($reasons === []) {
                continue;
            }

            $items[] = [
                'id' => Sqlite::integer($row, 'id'),
                'project_id' => Sqlite::integer($row, 'project_id'),
                'project_name' => Sqlite::text($row, 'project_name'),
                'title' => Sqlite::text($row, 'title'),
                'status' => $status,
                'assignee_id' => $assigneeId,
                'due_at' => $dueAt,
                'reasons' => $reasons,
            ];
        }

        $priority = [
            AttentionReason::CommentAttention->value => 0,
            AttentionReason::Blocked->value => 1,
            AttentionReason::Overdue->value => 2,
            AttentionReason::Unassigned->value => 3,
        ];
        usort($items, static fn (array $a, array $b): int =>
            [$priority[$a['reasons'][0]], $a['due_at'] === null, $a['due_at'], $a['id']]
            <=> [$priority[$b['reasons'][0]], $b['due_at'] === null, $b['due_at'], $b['id']]);

        return $items;
    }
}
