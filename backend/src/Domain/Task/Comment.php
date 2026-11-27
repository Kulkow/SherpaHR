<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use ProjectDesk\Domain\Exception\DomainException;
use ProjectDesk\Domain\Project\Project;
use ProjectDesk\Domain\User\UserId;

final readonly class Comment
{
    private function __construct(
        public ?CommentId $id,
        public TaskId $taskId,
        public UserId $userId,
        public CommentBody $body,
        public UtcTimestamp $createdAt,
        public CommentAttention $attention,
    ) {
    }

    public static function add(
        Task $task,
        Project $project,
        UserId $authorId,
        CommentBody $body,
        UtcTimestamp $createdAt,
        bool $requiresAttention = false,
    ): self {
        $task->assertBelongsTo($project);
        $project->assertWritableBy($authorId);
        if ($task->id === null) {
            throw new DomainException('Комментарий можно добавить только к сохранённой задаче.');
        }

        return new self(
            null,
            $task->id,
            $authorId,
            $body,
            $createdAt,
            $requiresAttention ? CommentAttention::Requested : CommentAttention::None,
        );
    }

    public static function restore(
        CommentId $id,
        TaskId $taskId,
        UserId $userId,
        CommentBody $body,
        UtcTimestamp $createdAt,
        CommentAttention $attention = CommentAttention::None,
    ): self {
        return new self($id, $taskId, $userId, $body, $createdAt, $attention);
    }

    public function resolveAttention(Task $task, Project $project, UserId $actorId): self
    {
        $task->assertBelongsTo($project);
        $project->assertWritableBy($actorId);

        if ($this->id === null || $task->id?->value !== $this->taskId->value) {
            throw new DomainException('Комментарий не относится к сохранённой задаче.');
        }
        if ($project->ownerId->value !== $actorId->value) {
            throw new DomainException('Отметку может снять только руководитель проекта.');
        }
        if ($this->attention === CommentAttention::None) {
            throw new DomainException('Комментарий не отмечен как требующий внимания.');
        }

        return new self($this->id, $this->taskId, $this->userId, $this->body, $this->createdAt, CommentAttention::Resolved);
    }
}
