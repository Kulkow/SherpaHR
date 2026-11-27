<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

use ProjectDesk\Application\ProjectDeskUseCases;
use ProjectDesk\Domain\Task\CommentId;
use ProjectDesk\Domain\Task\TaskId;
use ProjectDesk\Domain\User\User;
use RuntimeException;

final readonly class CommentController extends BaseController
{
    public function __construct(private ProjectDeskUseCases $useCases)
    {
    }

    public function create(User $actor, string $taskId): void
    {
        $id = new TaskId($this->routeId($taskId, 'Задача не найдена'));
        $comment = $this->useCases->addComment($actor, $id, $this->input(...));

        $this->respond([
            'id' => $comment->id->value ?? throw new RuntimeException('Missing saved comment ID.'),
            'task_id' => $comment->taskId->value,
            'user_id' => $comment->userId->value,
            'body' => $comment->body->value,
            'created_at' => $comment->createdAt->value,
            'author' => $actor->name,
            'attention' => $comment->attention->value,
        ], 201);
    }

    public function resolve(User $actor, string $taskId, string $commentId): void
    {
        $this->useCases->resolveCommentAttention(
            $actor,
            new TaskId($this->routeId($taskId, 'Задача не найдена')),
            new CommentId($this->routeId($commentId, 'Комментарий не найден')),
        );
        $this->respond(['attention' => 'resolved']);
    }
}
