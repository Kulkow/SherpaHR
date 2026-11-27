<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

use ProjectDesk\Domain\Exception\DomainException;
use ProjectDesk\Domain\Project\Project;
use ProjectDesk\Domain\Project\ProjectId;
use ProjectDesk\Domain\User\UserId;

final readonly class Task
{
    private function __construct(
        public ?TaskId $id,
        public ProjectId $projectId,
        public TaskTitle $title,
        public TaskDescription $description,
        public TaskStatus $status,
        public ?UserId $assigneeId,
        public ?DueAt $dueAt,
        public UtcTimestamp $updatedAt,
        public TaskVersion $version,
    ) {
    }

    public static function create(
        Project $project,
        UserId $actorId,
        TaskTitle $title,
        UtcTimestamp $updatedAt,
        TaskDescription $description = new TaskDescription(''),
        ?UserId $assigneeId = null,
        ?DueAt $dueAt = null,
    ): self {
        $project->assertWritableBy($actorId);
        $project->assertAllowsAssignee($assigneeId);

        return new self(
            null,
            $project->id,
            $title,
            $description,
            TaskStatus::Open,
            $assigneeId,
            $dueAt,
            $updatedAt,
            new TaskVersion(1),
        );
    }

    public static function restore(
        TaskId $id,
        ProjectId $projectId,
        TaskTitle $title,
        TaskDescription $description,
        TaskStatus $status,
        ?UserId $assigneeId,
        ?DueAt $dueAt,
        UtcTimestamp $updatedAt,
        TaskVersion $version,
    ): self {
        return new self($id, $projectId, $title, $description, $status, $assigneeId, $dueAt, $updatedAt, $version);
    }

    public function revise(
        Project $project,
        UserId $actorId,
        TaskVersion $expectedVersion,
        TaskTitle $title,
        TaskDescription $description,
        TaskStatus $status,
        ?UserId $assigneeId,
        ?DueAt $dueAt,
        UtcTimestamp $updatedAt,
    ): self {
        if ($this->id === null) {
            throw new DomainException('Сначала сохраните задачу.');
        }

        $this->assertBelongsTo($project);
        $project->assertWritableBy($actorId);

        if ($this->version->value !== $expectedVersion->value) {
            throw new DomainException('Версия задачи устарела.');
        }

        $project->assertAllowsAssignee($assigneeId);
        if ($this->status !== $status && !$this->status->canTransaction($status)) {
            throw new DomainException('Переход к выбранному статусу задачи недоступен.');
        }

        return new self(
            $this->id,
            $this->projectId,
            $title,
            $description,
            $status,
            $assigneeId,
            $dueAt,
            $updatedAt,
            $this->version->next(),
        );
    }

    public function assertBelongsTo(Project $project): void
    {
        if ($this->projectId->value !== $project->id->value) {
            throw new DomainException('Задача относится к другому проекту.');
        }
    }
}
