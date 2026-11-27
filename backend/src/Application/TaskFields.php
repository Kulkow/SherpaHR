<?php

declare(strict_types=1);

namespace ProjectDesk\Application;

use ProjectDesk\Domain\Exception\DomainException;
use ProjectDesk\Domain\Task\DueAt;
use ProjectDesk\Domain\Task\Task;
use ProjectDesk\Domain\Task\TaskDescription;
use ProjectDesk\Domain\Task\TaskStatus;
use ProjectDesk\Domain\Task\TaskTitle;
use ProjectDesk\Domain\Task\UtcTimestamp;
use ProjectDesk\Domain\User\UserId;

final readonly class TaskFields
{
    private function __construct(
        public TaskTitle $title,
        public TaskDescription $description,
        public TaskStatus $status,
        public ?UserId $assigneeId,
        public ?DueAt $dueAt,
    ) {
    }

    /** @param array<array-key, mixed> $payload */
    public static function fromInput(array $payload, ?Task $current = null): self
    {
        $allowed = ['title', 'description', 'status', 'assignee_id', 'due_at'];
        if ($current !== null) {
            $allowed[] = 'version';
        }
        if (array_diff(array_keys($payload), $allowed) !== []) {
            throw new DomainException('Неизвестные поля задачи.');
        }

        $title = array_key_exists('title', $payload) ? $payload['title'] : $current?->title->value;
        $description = array_key_exists('description', $payload)
            ? $payload['description']
            : ($current?->description->value ?? '');
        $status = array_key_exists('status', $payload) ? $payload['status'] : ($current?->status->value ?? 'open');
        $assignee = array_key_exists('assignee_id', $payload) ? $payload['assignee_id'] : $current?->assigneeId?->value;
        $dueAt = array_key_exists('due_at', $payload) ? $payload['due_at'] : $current?->dueAt?->timestamp->value;

        if (!is_string($title) || !is_string($description) || !is_string($status)) {
            throw new DomainException('Название, описание и статус задачи должны быть строками.');
        }
        if ($current === null && $status !== TaskStatus::Open->value) {
            throw new DomainException('Новая задача создаётся в статусе «Открыта».');
        }
        if ($assignee !== null && !is_int($assignee)) {
            throw new DomainException('Исполнитель должен быть задан целочисленным идентификатором или null.');
        }
        if ($dueAt !== null && !is_string($dueAt)) {
            throw new DomainException('Срок должен быть задан датой в UTC или null.');
        }

        return new self(
            new TaskTitle($title),
            new TaskDescription($description),
            TaskStatus::fromCode($status),
            $assignee === null ? null : new UserId($assignee),
            $dueAt === null ? null : new DueAt(new UtcTimestamp($dueAt)),
        );
    }
}
