<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

use ProjectDesk\Application\AttentionOverview;
use ProjectDesk\Application\ProjectDeskUseCases;
use ProjectDesk\Database;
use ProjectDesk\Infrastructure\Sqlite\CommentRepository;
use ProjectDesk\Infrastructure\Sqlite\ProjectDeskQueries;
use ProjectDesk\Infrastructure\Sqlite\ProjectRepository;
use ProjectDesk\Infrastructure\Sqlite\Sqlite;
use ProjectDesk\Infrastructure\Sqlite\TaskRepository;
use ProjectDesk\Infrastructure\Sqlite\UserRepository;

final class ControllerFactory
{
    private ?Sqlite $connection = null;

    public function users(): UserRepository
    {
        return new UserRepository($this->db());
    }

    public function health(): HealthController
    {
        $this->db();

        return new HealthController();
    }

    public function context(): ContextController
    {
        return new ContextController($this->useCases());
    }

    public function projects(): ProjectController
    {
        return new ProjectController($this->useCases());
    }

    public function tasks(): TaskController
    {
        return new TaskController($this->useCases());
    }

    public function comments(): CommentController
    {
        return new CommentController($this->useCases());
    }

    public function attention(): AttentionController
    {
        return new AttentionController(new AttentionOverview(new ProjectDeskQueries($this->db())));
    }

    private function useCases(): ProjectDeskUseCases
    {
        $db = $this->db();

        return new ProjectDeskUseCases(
            new ProjectRepository($db),
            new TaskRepository($db),
            new CommentRepository($db),
            new ProjectDeskQueries($db),
        );
    }

    private function db(): Sqlite
    {
        return $this->connection ??= new Sqlite(Database::connect());
    }
}
