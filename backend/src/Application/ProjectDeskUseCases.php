<?php

declare(strict_types=1);

namespace ProjectDesk\Application;

use Closure;
use ProjectDesk\Domain\Exception\DomainException;
use ProjectDesk\Domain\Project\Project;
use ProjectDesk\Domain\Project\ProjectId;
use ProjectDesk\Domain\Task\Comment;
use ProjectDesk\Domain\Task\CommentBody;
use ProjectDesk\Domain\Task\CommentId;
use ProjectDesk\Domain\Task\Task;
use ProjectDesk\Domain\Task\TaskId;
use ProjectDesk\Domain\Task\TaskVersion;
use ProjectDesk\Domain\Task\UtcTimestamp;
use ProjectDesk\Domain\User\User;
use ProjectDesk\Infrastructure\Sqlite\CommentRepository;
use ProjectDesk\Infrastructure\Sqlite\ProjectDeskQueries;
use ProjectDesk\Infrastructure\Sqlite\ProjectRepository;
use ProjectDesk\Infrastructure\Sqlite\TaskRepository;

final readonly class ProjectDeskUseCases
{
    public function __construct(
        private ProjectRepository $projects,
        private TaskRepository $tasks,
        private CommentRepository $comments,
        private ProjectDeskQueries $queries,
    ) {
    }

    /** @return array{user: array{id: int, name: string}, users: list<array<string, mixed>>, server_now: string, seeded_at: string} */
    public function context(User $actor): array
    {
        return [
            'user' => ['id' => $actor->id->value, 'name' => $actor->name],
            'users' => $this->queries->users(),
            'server_now' => self::now()->value,
            'seeded_at' => $this->queries->seededAt(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listProjects(User $actor): array
    {
        return $this->queries->projects($actor->id);
    }

    /** @return list<array<string, mixed>> */
    public function listTasks(User $actor, ProjectId $id): array
    {
        $this->requireProject($actor, $id);
        return $this->queries->tasks($id);
    }

    /** @return array{task: Task, comments: list<array<string, mixed>>} */
    public function taskDetail(User $actor, TaskId $id): array
    {
        [$task] = $this->requireTask($actor, $id);
        return ['task' => $task, 'comments' => $this->queries->comments($id)];
    }

    /** @param Closure(): array<array-key, mixed> $input */
    public function createTask(User $actor, ProjectId $id, Closure $input): Task
    {
        $project = $this->requireProject($actor, $id);
        $this->requireWritable($project);
        $fields = TaskFields::fromInput($input());

        return $this->tasks->insert(Task::create(
            $project,
            $actor->id,
            $fields->title,
            self::now(),
            $fields->description,
            $fields->assigneeId,
            $fields->dueAt,
        ));
    }

    /** @param Closure(): array<array-key, mixed> $input */
    public function reviseTask(User $actor, TaskId $id, Closure $input): Task
    {
        [$task, $project] = $this->requireTask($actor, $id);
        $this->requireWritable($project);
        $payload = $input();
        $fields = TaskFields::fromInput($payload, $task);
        $version = $payload['version'] ?? null;
        if (!is_int($version) || $version < 1) {
            throw new DomainException('Нужна положительная целочисленная версия задачи.');
        }
        if ($version !== $task->version->value) {
            throw self::versionConflict();
        }

        $expected = new TaskVersion($version);
        $updated = $task->revise(
            $project,
            $actor->id,
            $expected,
            $fields->title,
            $fields->description,
            $fields->status,
            $fields->assigneeId,
            $fields->dueAt,
            self::now(),
        );

        return $this->tasks->update($updated, $expected) ?? throw self::versionConflict();
    }

    /** @param Closure(): array<array-key, mixed> $input */
    public function addComment(User $actor, TaskId $id, Closure $input): Comment
    {
        [$task, $project] = $this->requireTask($actor, $id);
        $this->requireWritable($project);
        $payload = $input();
        $body = $payload['body'] ?? null;
        $requiresAttention = array_key_exists('requires_attention', $payload) ? $payload['requires_attention'] : false;
        if (!is_string($body)) {
            throw new DomainException('Комментарий должен быть строкой.');
        }

        if (!is_bool($requiresAttention)) {
            throw new DomainException('Отметка внимания должна быть логическим значением.');
        }

        return $this->comments->insert(Comment::add(
            $task, $project, $actor->id, new CommentBody($body), self::now(), $requiresAttention,
        ));
    }

    public function resolveCommentAttention(User $actor, TaskId $taskId, CommentId $commentId): void
    {
        [$task, $project] = $this->requireTask($actor, $taskId);
        $this->requireWritable($project);
        if ($project->ownerId->value !== $actor->id->value) {
            throw new UseCaseException('Отметку может снять только руководитель проекта.', UseCaseException::Forbidden);
        }

        $comment = $this->comments->find($taskId, $commentId)
            ?? throw new UseCaseException('Комментарий не найден', UseCaseException::NotFound);
        $resolved = $comment->resolveAttention($task, $project, $actor->id);
        if (!$this->comments->resolveAttention($resolved)) {
            throw new UseCaseException('Отметка комментария изменилась.', UseCaseException::Conflict);
        }
    }

    private function requireProject(User $actor, ProjectId $id): Project
    {
        $project = $this->projects->find($id);
        if ($project === null || !$project->hasMember($actor->id)) {
            throw new UseCaseException('Проект не найден', UseCaseException::NotFound);
        }

        return $project;
    }

    /** @return array{Task, Project} */
    private function requireTask(User $actor, TaskId $id): array
    {
        $task = $this->tasks->find($id);
        $project = $task === null ? null : $this->projects->find($task->projectId);
        if ($task === null || $project === null || !$project->hasMember($actor->id)) {
            throw new UseCaseException('Задача не найдена', UseCaseException::NotFound);
        }

        return [$task, $project];
    }

    private function requireWritable(Project $project): void
    {
        if ($project->archived) {
            throw new UseCaseException('Архивный проект доступен только для чтения', UseCaseException::Conflict);
        }
    }

    private static function now(): UtcTimestamp
    {
        return new UtcTimestamp(gmdate('Y-m-d\TH:i:s\Z'));
    }

    private static function versionConflict(): UseCaseException
    {
        return new UseCaseException(
            'Задача изменилась. Откройте её заново перед сохранением.',
            UseCaseException::Conflict,
        );
    }
}
