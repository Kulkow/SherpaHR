<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

use ProjectDesk\Application\ProjectDeskUseCases;
use ProjectDesk\Domain\Project\ProjectId;
use ProjectDesk\Domain\Task\Task;
use ProjectDesk\Domain\Task\TaskId;
use ProjectDesk\Domain\Task\TaskStatus;
use ProjectDesk\Domain\User\User;
use RuntimeException;

final readonly class TaskController extends BaseController
{
    public function __construct(private ProjectDeskUseCases $useCases)
    {
    }

    public function index(User $actor, string $projectId): void
    {
        $id = new ProjectId($this->routeId($projectId, 'Проект не найден'));
        $this->respond($this->useCases->listTasks($actor, $id));
    }

    public function create(User $actor, string $projectId): void
    {
        $id = new ProjectId($this->routeId($projectId, 'Проект не найден'));
        $task = $this->useCases->createTask($actor, $id, $this->input(...));
        $this->respond($this->taskData($task), 201);
    }

    public function show(User $actor, string $taskId): void
    {
        $id = new TaskId($this->routeId($taskId, 'Задача не найдена'));
        $detail = $this->useCases->taskDetail($actor, $id);
        $this->respond([...$this->taskData($detail['task']), 'comments' => $detail['comments']]);
    }

    public function update(User $actor, string $taskId): void
    {
        $id = new TaskId($this->routeId($taskId, 'Задача не найдена'));
        $task = $this->useCases->reviseTask($actor, $id, $this->input(...));
        $this->respond($this->taskData($task));
    }

    /** @return array<string, int|string|list<string>|null> */
    private function taskData(Task $task): array
    {
        return [
            'id' => $task->id->value ?? throw new RuntimeException('Missing saved task ID.'),
            'project_id' => $task->projectId->value,
            'title' => $task->title->value,
            'description' => $task->description->value,
            'status' => $task->status->value,
            'allowed_statuses' => array_map(
                static fn (TaskStatus $status): string => $status->value,
                $task->status->allowTransactions(),
            ),
            'assignee_id' => $task->assigneeId?->value,
            'due_at' => $task->dueAt?->timestamp->value,
            'updated_at' => $task->updatedAt->value,
            'version' => $task->version->value,
        ];
    }
}
