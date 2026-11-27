<?php

declare(strict_types=1);

namespace ProjectDesk\Infrastructure\Sqlite;

use LogicException;
use ProjectDesk\Domain\Task\Comment;
use ProjectDesk\Domain\Task\CommentAttention;
use ProjectDesk\Domain\Task\CommentBody;
use ProjectDesk\Domain\Task\CommentId;
use ProjectDesk\Domain\Task\TaskId;
use ProjectDesk\Domain\Task\UtcTimestamp;
use ProjectDesk\Domain\User\UserId;
use RuntimeException;

final readonly class CommentRepository
{
    public function __construct(private Sqlite $db)
    {
    }

    public function insert(Comment $comment): Comment
    {
        if ($comment->id !== null) {
            throw new LogicException('Only a new comment can be inserted.');
        }

        $row = $this->db->one(
            'INSERT INTO comments (task_id, user_id, body, created_at, attention) VALUES (?, ?, ?, ?, ?) RETURNING id',
            [$comment->taskId->value, $comment->userId->value, $comment->body->value,
                $comment->createdAt->value, $comment->attention->value],
        ) ?? throw new RuntimeException('SQLite did not return the inserted comment.');

        return Comment::restore(
            new CommentId(Sqlite::integer($row, 'id')),
            $comment->taskId,
            $comment->userId,
            $comment->body,
            $comment->createdAt,
            $comment->attention,
        );
    }

    public function find(TaskId $taskId, CommentId $id): ?Comment
    {
        $row = $this->db->one(
            'SELECT user_id, body, created_at, attention FROM comments WHERE id = ? AND task_id = ?',
            [$id->value, $taskId->value],
        );
        if ($row === null) {
            return null;
        }

        return Comment::restore(
            $id,
            $taskId,
            new UserId(Sqlite::integer($row, 'user_id')),
            new CommentBody(Sqlite::text($row, 'body')),
            new UtcTimestamp(Sqlite::text($row, 'created_at')),
            CommentAttention::from(Sqlite::text($row, 'attention')),
        );
    }

    public function resolveAttention(Comment $comment): bool
    {
        $id = $comment->id ?? throw new LogicException('Only a saved comment can be updated.');
        $row = $this->db->one(
            'UPDATE comments SET attention = ?
             WHERE id = ? AND task_id = ? AND attention IN (?, ?) RETURNING id',
            [CommentAttention::Resolved->value, $id->value, $comment->taskId->value,
                CommentAttention::Requested->value, CommentAttention::Resolved->value],
        );

        return $row !== null;
    }
}
